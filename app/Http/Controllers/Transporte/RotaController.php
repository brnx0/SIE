<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreRotaRequest;
use App\Models\Parametro\AnoLetivo;
use App\Models\Transporte\Rota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RotaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']);

        // 'all' = "Todos os anos". Sem anl_id (1ª carga) → maior ano cadastrado.
        if ($request->input('anl_id') !== 'all' && ! $request->filled('anl_id') && $anosLetivos->isNotEmpty()) {
            $request->merge(['anl_id' => $anosLetivos->first()->anl_id]);
        }

        $rotas = $this->baseQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/rotas/Index', [
            'rotas'       => $rotas,
            'anosLetivos' => $anosLetivos,
            'turnos'      => Rota::TURNOS,
            'filters'     => array_merge(
                $request->only(['search', 'anl_id', 'turno']),
                ['per_page' => $perPage],
            ),
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        if ($request->input('anl_id') !== 'all' && ! $request->filled('anl_id')) {
            $anlId = AnoLetivo::orderByDesc('anl_ano')->value('anl_id');
            if ($anlId) {
                $request->merge(['anl_id' => $anlId]);
            }
        }

        $rotas = $this->baseQuery($request)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($rotas, $request);
        }

        return $this->exportCsv($rotas);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/rotas/Create', [
            'anosLetivos' => AnoLetivo::paraCadastro()->orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'turnos'      => Rota::TURNOS,
            'zonas'       => Rota::ZONAS,
        ]);
    }

    public function store(StoreRotaRequest $request): RedirectResponse
    {
        $rota = Rota::create($request->validated());

        return to_route('transporte.rotas.edit', $rota)->with('success', 'Rota cadastrada com sucesso.');
    }

    public function edit(Rota $rota): Response
    {
        $rota->load([
            'anoLetivo:anl_id,anl_ano',
            'veiculo:vei_id,vei_placa,vei_capacidade,vei_fl_ativo',
            'motorista:fun_id,fun_nome,fun_cnh_categoria,fun_cnh_dt_validade,fun_fl_ativo',
            'monitor:fun_id,fun_nome,fun_fl_ativo',
            'pontos.escola:esc_id,esc_nome',
            'pontos.bairro:bai_id,bai_nome,bai_mun_id',
        ]);

        return Inertia::render('transporte/rotas/Edit', [
            'rota'        => $rota,
            'pontos'      => $rota->pontos->map(fn ($p) => [
                'pto_id'         => $p->pto_id,
                'pto_rot_id'     => $p->pto_rot_id,
                'pto_ordem'      => $p->pto_ordem,
                'pto_nome'       => $p->pto_nome,
                'pto_cep'        => $p->pto_cep,
                'pto_endereco'   => $p->pto_endereco,
                'pto_bai_id'     => $p->pto_bai_id,
                'pto_latitude'   => $p->pto_latitude,
                'pto_longitude'  => $p->pto_longitude,
                'pto_hr_prevista' => $p->pto_hr_prevista ? substr($p->pto_hr_prevista, 0, 5) : null,
                'pto_esc_id'     => $p->pto_esc_id,
                'bairro'         => $p->bairro ? ['bai_id' => $p->bairro->bai_id, 'bai_nome' => $p->bairro->bai_nome, 'bai_mun_id' => $p->bairro->bai_mun_id] : null,
                'escola'         => $p->escola ? ['esc_id' => $p->escola->esc_id, 'esc_nome' => $p->escola->esc_nome] : null,
                'total_passageiros' => $p->passageiros()->count(),
            ]),
            'passageiros' => $this->passageirosSerializados($rota),
            'anosLetivos' => AnoLetivo::paraCadastro([$rota->rot_anl_id])->orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'turnos'      => Rota::TURNOS,
            'zonas'       => Rota::ZONAS,
        ]);
    }

    public function update(StoreRotaRequest $request, Rota $rota): RedirectResponse
    {
        $rota->update($request->validated());

        return to_route('transporte.rotas.edit', $rota)->with('success', 'Rota atualizada com sucesso.');
    }

    public function destroy(Rota $rota): RedirectResponse
    {
        if ($rota->pontos()->exists() || $rota->passageiros()->exists() || $rota->diarios()->exists()) {
            return back()->with('error', 'Não é possível excluir: a rota possui pontos, passageiros ou diários vinculados. Inative-a caso não queira mais utilizá-la.');
        }

        return $this->safeDelete($rota)
            ?? to_route('transporte.rotas.index')->with('success', 'Rota removida com sucesso.');
    }

    /**
     * Serializa os passageiros da rota com dados do aluno/usuário externo,
     * ponto de embarque e — para alunos da rede — escola/turma da matrícula
     * ativa no ano letivo da rota.
     */
    private function passageirosSerializados(Rota $rota)
    {
        $passageiros = $rota->passageiros()
            ->with([
                'ponto:pto_id,pto_nome,pto_ordem',
                'aluno:aln_id,aln_nome,aln_nr_matricula',
                'usuarioExterno:tue_id,tue_nome',
            ])
            ->get();

        $alnIds = $passageiros->pluck('pas_aln_id')->filter()->unique()->all();
        $matriculas = $this->matriculaInfoAlunos($alnIds, $rota->rot_anl_id);

        return $passageiros->map(fn ($p) => [
            'pas_id'        => $p->pas_id,
            'pas_rot_id'    => $p->pas_rot_id,
            'pas_pto_id'    => $p->pas_pto_id,
            'pas_aln_id'    => $p->pas_aln_id,
            'pas_tue_id'    => $p->pas_tue_id,
            'pas_dt_inicio' => $p->pas_dt_inicio,
            'pas_dt_fim'    => $p->pas_dt_fim,
            'pas_obs'       => $p->pas_obs,
            'ponto'         => $p->ponto ? ['pto_id' => $p->ponto->pto_id, 'pto_nome' => $p->ponto->pto_nome, 'pto_ordem' => $p->ponto->pto_ordem] : null,
            'aluno'         => $p->aluno ? [
                'aln_id'          => $p->aluno->aln_id,
                'aln_nome'        => $p->aluno->aln_nome,
                'aln_nr_matricula' => $p->aluno->aln_nr_matricula,
                'turma'           => $matriculas[$p->aluno->aln_id]['tur_nome'] ?? null,
                'escola'          => $matriculas[$p->aluno->aln_id]['esc_nome'] ?? null,
            ] : null,
            'usuario_externo' => $p->usuarioExterno ? ['tue_id' => $p->usuarioExterno->tue_id, 'tue_nome' => $p->usuarioExterno->tue_nome] : null,
        ]);
    }

    /**
     * Escola/turma da matrícula ativa (sem saída) por aluno, no ano letivo dado.
     * @return array<int, array{tur_nome:string, esc_nome:string}>
     */
    private function matriculaInfoAlunos(array $alnIds, int $anlId): array
    {
        if (empty($alnIds)) {
            return [];
        }

        return DB::table('edu_turma_aluno')
            ->join('edu_turma', 'edu_turma_aluno.tma_tur_id', '=', 'edu_turma.tur_id')
            ->leftJoin('edu_escola', 'edu_turma.tur_esc_id', '=', 'edu_escola.esc_id')
            ->whereIn('edu_turma_aluno.tma_aln_id', $alnIds)
            ->where('edu_turma.tur_anl_id', $anlId)
            ->whereNull('edu_turma_aluno.tma_deleted_at')
            ->whereNull('edu_turma_aluno.tma_tas_cod_saida')
            ->get(['edu_turma_aluno.tma_aln_id as aln_id', 'edu_turma.tur_nome', 'edu_escola.esc_nome'])
            ->keyBy('aln_id')
            ->map(fn ($r) => ['tur_nome' => $r->tur_nome, 'esc_nome' => $r->esc_nome])
            ->all();
    }

    private function baseQuery(Request $request)
    {
        return Rota::query()
            ->with([
                'anoLetivo:anl_id,anl_ano',
                'veiculo:vei_id,vei_placa,vei_capacidade',
                'motorista:fun_id,fun_nome,fun_cnh_categoria,fun_cnh_dt_validade',
                'monitor:fun_id,fun_nome',
            ])
            ->withCount('passageiros')
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->integer('anl_id'), fn ($q, $a) => $q->where('rot_anl_id', $a))
            ->when($request->string('turno')->toString(), fn ($q, $t) => $q->where('rot_turno', $t))
            ->orderByDesc('rot_anl_id')
            ->orderBy('rot_nome');
    }

    private function exportCsv($rotas): StreamedResponse
    {
        $filename = 'transporte_rotas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rotas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Rota', 'Ano Letivo', 'Turno', 'Zona', 'Veículo', 'Capacidade', 'Motorista', 'Monitor', 'Passageiros', 'Situação'], ';');
            foreach ($rotas as $r) {
                fputcsv($out, [
                    $r->rot_nome,
                    $r->anoLetivo?->anl_ano ?? '',
                    Rota::TURNOS[$r->rot_turno] ?? $r->rot_turno,
                    $r->rot_zona ? (Rota::ZONAS[$r->rot_zona] ?? $r->rot_zona) : '',
                    $r->veiculo?->vei_placa ?? '',
                    $r->veiculo?->vei_capacidade ?? '',
                    $r->motorista?->fun_nome ?? '',
                    $r->monitor?->fun_nome ?? '',
                    $r->passageiros_count,
                    $r->rot_fl_ativo ? 'Ativa' : 'Inativa',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($rotas, Request $request): HttpResponse
    {
        $collection = collect($rotas);
        $filename   = 'transporte_rotas_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_rotas_pdf', [
            'rotas'         => $collection,
            'turnos'        => Rota::TURNOS,
            'zonas'         => Rota::ZONAS,
            'total'         => $collection->count(),
            'totalAtivas'   => $collection->where('rot_fl_ativo', true)->count(),
            'totalInativas' => $collection->where('rot_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
