<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\StorePlanoAeeRequest;
use App\Models\Diario\DiarioPlanoAee;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanoAeeController extends Controller
{
    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        $planos = DiarioPlanoAee::query()
            ->where('dae_fun_id', $funId)
            ->with([
                'turma:tur_id,tur_nome',
                'escola:esc_id,esc_nome',
                'funcionario:fun_id,fun_nome',
            ])
            ->when($request->input('search'), fn ($q, $s) => $q->where('dae_tema', 'ilike', "%{$s}%"))
            ->when($request->input('status'), fn ($q, $st) => $q->where('dae_status', $st))
            ->when($request->input('tur_id'), fn ($q, $t) => $q->where('dae_tur_id', (int) $t))
            ->orderByDesc('dae_dt_inicio')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($planos, $request);
        }

        return $this->exportCsv($planos);
    }

    private function exportCsv($planos): StreamedResponse
    {
        $filename = 'planos_aee_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($planos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Status', 'Professor', 'Escola', 'Turma', 'Tema', 'Data Inicial', 'Data Final'], ';');
            foreach ($planos as $p) {
                fputcsv($out, [
                    ucfirst($p->dae_status ?? ''),
                    $p->funcionario?->fun_nome ?? '',
                    $p->escola?->esc_nome ?? '',
                    $p->turma?->tur_nome ?? '',
                    $p->dae_tema,
                    optional($p->dae_dt_inicio)?->format('d/m/Y') ?? '',
                    optional($p->dae_dt_fim)?->format('d/m/Y') ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($planos, Request $request): HttpResponse
    {
        $collection = collect($planos);
        $filename = 'planos_aee_' . now()->format('Ymd_His') . '.pdf';

        $statusCounts = $collection->groupBy('dae_status')->map->count()->all();

        $filtroParts = [];
        if ($request->input('search')) $filtroParts[] = 'Busca: "' . $request->input('search') . '"';
        if ($request->input('status')) $filtroParts[] = 'Status: ' . ucfirst($request->input('status'));

        $html = view('exports.planos_lista_pdf', [
            'titulo'          => 'Planos de Aula AEE',
            'planos'          => $collection,
            'total'           => $collection->count(),
            'statusCounts'    => $statusCounts,
            'mostraProfessor' => false,
            'filtroDescricao' => implode(' · ', $filtroParts),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 10, 'margin_right' => 10, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function index(Request $request): Response
    {
        $this->abortIfNotProfessor();

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $funId = (int) $request->user()->fun_id;

        $planos = DiarioPlanoAee::query()
            ->where('dae_fun_id', $funId)
            ->with([
                'turma:tur_id,tur_nome',
                'escola:esc_id,esc_nome',
                'anoLetivo:anl_id,anl_ano',
            ])
            ->when($request->input('search'), function ($q, $s) {
                $q->where('dae_tema', 'ilike', "%{$s}%");
            })
            ->when($request->input('status'), fn ($q, $st) => $q->where('dae_status', $st))
            ->when($request->input('tur_id'), fn ($q, $t) => $q->where('dae_tur_id', (int) $t))
            ->orderByDesc('dae_dt_inicio')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('diario/planos-aee/Index', [
            'planos'  => $planos,
            'filters' => [
                'search'   => $request->input('search', ''),
                'status'   => $request->input('status', ''),
                'tur_id'   => $request->input('tur_id', ''),
                'per_page' => $perPage,
            ],
            'statuses' => DiarioPlanoAee::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos-aee/Form', [
            'plano'       => null,
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
        ]);
    }

    public function store(StorePlanoAeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['dae_fun_id'] = (int) $request->user()->fun_id;
        $data['dae_status'] = DiarioPlanoAee::STATUS_PENDENTE;

        $plano = DiarioPlanoAee::create($data);

        return to_route('diario.planos-aee.edit', $plano)->with('success', 'Plano AEE cadastrado com sucesso.');
    }

    public function edit(DiarioPlanoAee $plano, Request $request): Response
    {
        $this->abortIfNotProfessor();
        $this->abortIfNotOwner($plano, $request);

        $plano->load([
            'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'validadoPor:id,name',
        ]);

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos-aee/Form', [
            'plano'       => $plano,
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
        ]);
    }

    public function update(StorePlanoAeeRequest $request, DiarioPlanoAee $plano): RedirectResponse
    {
        $this->abortIfNotOwner($plano, $request);

        $data = $request->validated();

        if ($plano->dae_status === DiarioPlanoAee::STATUS_CORRECAO) {
            $data['dae_status'] = DiarioPlanoAee::STATUS_PENDENTE;
        }

        $plano->update($data);

        return to_route('diario.planos-aee.edit', $plano)->with('success', 'Plano AEE atualizado.');
    }

    public function destroy(DiarioPlanoAee $plano, Request $request): RedirectResponse
    {
        $this->abortIfNotOwner($plano, $request);

        if (! $plano->isPendente()) {
            return back()->with('error', 'Plano só pode ser excluído enquanto estiver pendente.');
        }

        return $this->safeDelete($plano)
            ?? to_route('diario.planos-aee.index')->with('success', 'Plano removido.');
    }

    public function pdf(DiarioPlanoAee $plano, Request $request): HttpResponse
    {
        $this->abortIfNotOwner($plano, $request);

        $plano->load([
            'funcionario:fun_id,fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'turma:tur_id,tur_nome',
        ]);

        $entidade = DB::table('cfg_parametros_entidade')->value('par_nome_entidade');

        $html = view('exports.diario_plano_aee_pdf', [
            'plano'    => $plano,
            'entidade' => $entidade,
        ])->render();

        $filename = "plano_aee_{$plano->dae_id}_" . now()->format('Ymd_His') . '.pdf';

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_top'    => 4,
            'margin_bottom' => 8,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    // ============ Endpoints lookup ============

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        $escolas = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 'l.lot_esc_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_fl_ativo', true)
            ->whereJsonContains('l.lot_funcoes_sala_aula', 'Docente AEE')
            ->select('e.esc_id', 'e.esc_nome')
            ->distinct()
            ->orderBy('e.esc_nome')
            ->get();

        return response()->json($escolas);
    }

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        if (! $escId) {
            return response()->json([]);
        }

        $temLotacao = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_esc_id', $escId)
            ->where('l.lot_fl_ativo', true)
            ->whereJsonContains('l.lot_funcoes_sala_aula', 'Docente AEE')
            ->exists();

        if (! $temLotacao) {
            return response()->json([]);
        }

        $turmas = DB::table('edu_turma as t')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'AEE')
            ->where('t.tur_esc_id', $escId)
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->select('t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 'e.esc_nome')
            ->orderBy('t.tur_nome')
            ->get();

        return response()->json($turmas);
    }

    // ============ Helpers ============

    private function abortIfNotProfessor(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito a professores.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor'), 403, 'Acesso restrito a professores.');
    }

    private function abortIfNotOwner(DiarioPlanoAee $plano, Request $request): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }
        abort_unless((int) $plano->dae_fun_id === (int) $request->user()->fun_id, 403);
    }

    private function resumoProfessor($user): array
    {
        $fun = DB::table('edu_funcionario')->where('fun_id', $user->fun_id)->first(['fun_id', 'fun_nome']);
        return [
            'fun_id'   => $fun?->fun_id,
            'fun_nome' => $fun?->fun_nome,
        ];
    }

    private function anosLetivosDoProfessor(int $funId): array
    {
        // Períodos de lotação ativa "Docente AEE" do professor
        $lotacoes = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_fl_ativo', true)
            ->whereJsonContains('l.lot_funcoes_sala_aula', 'Docente AEE')
            ->select('l.lot_dt_inicio', 'l.lot_dt_fim')
            ->get();

        if ($lotacoes->isEmpty()) {
            return [];
        }

        // Ano letivo conta se período da lotação intersecta com período do ano
        $query = AnoLetivo::query()->whereNull('anl_deleted_at');
        $query->where(function ($outer) use ($lotacoes) {
            foreach ($lotacoes as $lot) {
                $outer->orWhere(function ($q) use ($lot) {
                    $q->where(function ($qq) use ($lot) {
                        $qq->whereNull('anl_dt_fim')
                            ->orWhere('anl_dt_fim', '>=', $lot->lot_dt_inicio);
                    });
                    if ($lot->lot_dt_fim) {
                        $q->where('anl_dt_inicio_ano', '<=', $lot->lot_dt_fim);
                    }
                });
            }
        });

        return $query
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->toArray();
    }
}
