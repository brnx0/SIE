<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Http\Requests\Turma\StoreTurmaRequest;
use App\Models\Disciplina\Disciplina;
use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\GradeDisciplinar;
use App\Models\Parametro\GradeHorario;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TurmaController extends Controller
{
    private const TURNOS = [
        'm' => 'Manhã',
        't' => 'Tarde',
        'n' => 'Noite',
        'i' => 'Integral',
    ];

    private const TURNO_MAP = [
        'MATUTINO'   => 'm',
        'VESPERTINO' => 't',
        'NOTURNO'    => 'n',
    ];

    public function index(Request $request): Response
    {
        $user    = auth()->user();
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']);

        // Sem filtro de ano letivo explícito → usa o maior ano cadastrado
        if (! $request->filled('anl_id') && $anosLetivos->isNotEmpty()) {
            $request->merge(['anl_id' => $anosLetivos->first()->anl_id]);
        }

        $turmas = $this->baseQuery($request, $user)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('turmas/Index', [
            'turmas'      => $turmas,
            'filters'     => array_merge(
                $request->only(['esc_id', 'anl_id', 'seg_id', 'turno', 'situacao']),
                ['per_page' => $perPage],
            ),
            'anosLetivos' => $anosLetivos,
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : [],
            'isAdmin'     => $user->isAdmin(),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $user = auth()->user();

        if (! $request->filled('anl_id')) {
            $anlId = AnoLetivo::orderByDesc('anl_ano')->value('anl_id');
            if ($anlId) {
                $request->merge(['anl_id' => $anlId]);
            }
        }

        $turmas = $this->baseQuery($request, $user)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($turmas, $request);
        }

        return $this->exportCsv($turmas);
    }

    public function create(): Response
    {
        $user = auth()->user();

        return Inertia::render('turmas/Create', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : [],
            'isAdmin'     => $user->isAdmin(),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
        ]);
    }

    public function store(StoreTurmaRequest $request): RedirectResponse
    {
        $turma = Turma::create($request->validated());

        return to_route('turmas.edit', $turma)->with('success', 'Turma cadastrada com sucesso.');
    }

    public function edit(Turma $turma): Response
    {
        $user = auth()->user();
        $turma->load([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'segmento:seg_id,seg_nome_reduzido',
            'serie:ser_id,ser_nome',
            'professores.funcionario:fun_id,fun_nome',
            'professores.disciplina:dis_id,dis_nome',
            'professoresApoio.funcionario:fun_id,fun_nome,fun_cd_censo',
            'horarios.funcionario:fun_id,fun_nome',
            'horarios.disciplina:dis_id,dis_nome',
        ]);
        return Inertia::render('turmas/Edit', [
            'turma'                  => $turma,
            'total_matriculados'     => $turma->matriculas()->whereNull('tma_tas_cod_saida')->count(),
            'professoresApoio'       => $turma->professoresApoio->map(fn ($a) => [
                'tpa_id'      => $a->tpa_id,
                'tpa_tur_id'  => $a->tpa_tur_id,
                'tpa_fun_id'  => $a->tpa_fun_id,
                'tpa_obs'     => $a->tpa_obs,
                'funcionario' => $a->funcionario ? [
                    'fun_id'       => $a->funcionario->fun_id,
                    'fun_nome'     => $a->funcionario->fun_nome,
                    'fun_cd_censo' => $a->funcionario->fun_cd_censo,
                ] : null,
            ]),
            'anosLetivos'            => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'                => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : [],
            'isAdmin'                => $user->isAdmin(),
            'userEscola'             => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'disciplinas'            => Disciplina::whereIn('dis_id',
                    GradeDisciplinar::where('grd_anl_id', $turma->tur_anl_id)
                        ->where('grd_ser_id', $turma->tur_ser_id)
                        ->where('grd_fl_ativo', true)
                        ->pluck('grd_dis_id')
                )->orderBy('dis_nome')->get(['dis_id', 'dis_nome']),
            'professoresDisponiveis' => Funcionario::whereHas('admissoes.lotacoes', fn ($q) =>
                $q->where('lot_esc_id', $turma->tur_esc_id)
            )->orderBy('fun_nome')->get(['fun_id', 'fun_nome']),
            'gradeHorarios'          => GradeHorario::where('grh_seg_id', $turma->tur_seg_id)
                ->when($turma->tur_turno !== 'INTEGRAL', fn ($q) => $q->where('grh_turno', self::TURNO_MAP[$turma->tur_turno] ?? $turma->tur_turno))
                ->orderBy('grh_ordem')
                ->get(['grh_id', 'grh_turno', 'grh_hora', 'grh_ordem']),
        ]);
    }

    public function update(StoreTurmaRequest $request, Turma $turma): RedirectResponse
    {
        $turma->update($request->validated());

        return to_route('turmas.edit', $turma)->with('success', 'Turma atualizada com sucesso.');
    }

    public function destroy(Turma $turma): RedirectResponse
    {
        return $this->safeDelete($turma)
            ?? to_route('turmas.index')->with('success', 'Turma removida com sucesso.');
    }

    private function baseQuery(Request $request, $user)
    {
        $query = Turma::with([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'segmento:seg_id,seg_nome_reduzido',
            'serie:ser_id,ser_nome',
        ]);

        if (! $user->isAdmin()) {
            $query->where('tur_esc_id', $user->esc_id);
        } elseif ($escId = $request->integer('esc_id')) {
            $query->where('tur_esc_id', $escId);
        }

        if ($anlId = $request->integer('anl_id'))       $query->where('tur_anl_id', $anlId);
        if ($segId = $request->integer('seg_id'))       $query->where('tur_seg_id', $segId);
        if ($turno = $request->string('turno')->toString())       $query->where('tur_turno', $turno);
        if ($sit   = $request->string('situacao')->toString())    $query->where('tur_situacao', $sit);

        return $query
            ->orderBy('tur_anl_id', 'desc')
            ->orderBy('tur_esc_id')
            ->orderBy('tur_turno')
            ->orderBy('tur_nome');
    }

    private function exportCsv($turmas): StreamedResponse
    {
        $filename = 'turmas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($turmas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Turma', 'Escola', 'Ano Letivo', 'Segmento', 'Série', 'Turno', 'Capacidade', 'Situação'], ';');
            foreach ($turmas as $t) {
                fputcsv($out, [
                    $t->tur_nome,
                    $t->escola?->esc_nome ?? '',
                    $t->anoLetivo?->anl_ano ?? '',
                    $t->segmento?->seg_nome_reduzido ?? '',
                    $t->serie?->ser_nome ?? '',
                    self::TURNOS[$t->tur_turno] ?? $t->tur_turno,
                    $t->tur_capacidade ?? '',
                    $t->tur_situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($turmas, Request $request): HttpResponse
    {
        $collection = collect($turmas);
        $filename   = 'turmas_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.turmas_pdf', [
            'turmas'        => $collection,
            'turnos'        => self::TURNOS,
            'total'         => $collection->count(),
            'totalAbertas'  => $collection->where('tur_situacao', 'ABERTA')->count(),
            'totalEncerradas' => $collection->where('tur_situacao', 'ENCERRADA')->count(),
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
