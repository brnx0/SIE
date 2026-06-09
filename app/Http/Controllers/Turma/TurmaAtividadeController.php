<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Http\Requests\Turma\StoreTurmaAtividadeRequest;
use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\AnoLetivo;
use App\Models\Turma\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TurmaAtividadeController extends Controller
{
    private const TURNOS = [
        'INTEGRAL'   => 'Integral',
        'MATUTINO'   => 'Matutino',
        'VESPERTINO' => 'Vespertino',
        'NOTURNO'    => 'Noturno',
    ];

    public function index(Request $request): Response
    {
        $user    = auth()->user();
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']);

        if (! $request->filled('anl_id') && $anosLetivos->isNotEmpty()) {
            $request->merge(['anl_id' => $anosLetivos->first()->anl_id]);
        }

        $turmas = $this->baseQuery($request, $user)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('turmas-atividade/Index', [
            'turmas'      => $turmas,
            'filters'     => array_merge(
                $request->only(['esc_id', 'anl_id', 'turno', 'situacao']),
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

        return Inertia::render('turmas-atividade/Create', [
            'anosLetivos' => AnoLetivo::paraCadastro()->orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : [],
            'isAdmin'     => $user->isAdmin(),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
        ]);
    }

    public function store(StoreTurmaAtividadeRequest $request): RedirectResponse
    {
        $turma = Turma::create($request->validated());

        return to_route('turmas-atividade.edit', $turma)->with('success', 'Turma de Atividade cadastrada com sucesso.');
    }

    public function edit(Turma $turmaAtividade): Response
    {
        $turma = $this->guard($turmaAtividade);
        $user  = auth()->user();

        $turma->load([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'professores.funcionario:fun_id,fun_nome',
        ]);

        return Inertia::render('turmas-atividade/Edit', [
            'turma'                  => $turma,
            'total_matriculados'     => $turma->matriculas()->whereNull('tma_tas_cod_saida')->count(),
            'professores'            => $turma->professores->map(fn ($p) => [
                'tup_id'      => $p->tup_id,
                'tup_tur_id'  => $p->tup_tur_id,
                'tup_fun_id'  => $p->tup_fun_id,
                'funcionario' => $p->funcionario ? [
                    'fun_id'   => $p->funcionario->fun_id,
                    'fun_nome' => $p->funcionario->fun_nome,
                ] : null,
            ]),
            'anosLetivos'            => AnoLetivo::paraCadastro([$turma->tur_anl_id])->orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'                => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : [],
            'isAdmin'                => $user->isAdmin(),
            'userEscola'             => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'professoresDisponiveis' => Funcionario::whereHas('admissoes.lotacoes', fn ($q) =>
                $q->where('lot_esc_id', $turma->tur_esc_id)
                  ->whereJsonContains('lot_funcoes_sala_aula', 'Monitor de atividade complementar')
            )->orderBy('fun_nome')->get(['fun_id', 'fun_nome']),
        ]);
    }

    public function update(StoreTurmaAtividadeRequest $request, Turma $turmaAtividade): RedirectResponse
    {
        $turma = $this->guard($turmaAtividade);
        $turma->update($request->validated());

        return to_route('turmas-atividade.edit', $turma)->with('success', 'Turma de Atividade atualizada com sucesso.');
    }

    public function destroy(Turma $turmaAtividade): RedirectResponse
    {
        $turma = $this->guard($turmaAtividade);

        return $this->safeDelete($turma)
            ?? to_route('turmas-atividade.index')->with('success', 'Turma de Atividade removida com sucesso.');
    }

    private function guard(Turma $turma): Turma
    {
        abort_unless($turma->tur_modalidade === Turma::MODALIDADE_ATIVIDADE, 404);

        return $turma;
    }

    private function baseQuery(Request $request, $user)
    {
        $query = Turma::atividade()->with([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
        ]);

        if (! $user->isAdmin()) {
            $query->where('tur_esc_id', $user->esc_id);
        } elseif ($escId = $request->integer('esc_id')) {
            $query->where('tur_esc_id', $escId);
        }

        if ($anlId = $request->integer('anl_id'))               $query->where('tur_anl_id', $anlId);
        if ($turno = $request->string('turno')->toString())     $query->where('tur_turno', $turno);
        if ($sit   = $request->string('situacao')->toString())  $query->where('tur_situacao', $sit);

        return $query
            ->orderBy('tur_anl_id', 'desc')
            ->orderBy('tur_esc_id')
            ->orderBy('tur_turno')
            ->orderBy('tur_nome');
    }

    private function exportCsv($turmas): StreamedResponse
    {
        $filename = 'turmas_atividade_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($turmas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Turma', 'Escola', 'Ano Letivo', 'Turno', 'Capacidade', 'Situação'], ';');
            foreach ($turmas as $t) {
                fputcsv($out, [
                    $t->tur_nome,
                    $t->escola?->esc_nome ?? '',
                    $t->anoLetivo?->anl_ano ?? '',
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
        $filename   = 'turmas_atividade_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.turmas_atividade_pdf', [
            'turmas'          => $collection,
            'turnos'          => self::TURNOS,
            'total'           => $collection->count(),
            'totalAbertas'    => $collection->where('tur_situacao', 'ABERTA')->count(),
            'totalEncerradas' => $collection->where('tur_situacao', 'ENCERRADA')->count(),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
