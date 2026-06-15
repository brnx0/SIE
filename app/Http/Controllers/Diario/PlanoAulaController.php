<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\StorePlanoAulaRequest;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Diario\DiarioPlanoIndicador;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\Serie\SerieIndicador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanoAulaController extends Controller
{
    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        $planos = DiarioPlanoAula::query()
            ->where('dpa_fun_id', $funId)
            ->with([
                'turma:tur_id,tur_nome,tur_ser_id',
                'turma.serie:ser_id,ser_nome',
                'disciplina:dis_id,dis_nome',
                'unidade:uni_id,uni_numero,uni_tipo',
                'escola:esc_id,esc_nome',
                'funcionario:fun_id,fun_nome',
            ])
            ->when($request->input('search'), fn ($q, $s) => $q->where('dpa_tema', 'ilike', "%{$s}%"))
            ->when($request->input('status'), fn ($q, $st) => $q->where('dpa_status', $st))
            ->when($request->input('tur_id'), fn ($q, $t) => $q->where('dpa_tur_id', (int) $t))
            ->when($request->input('dis_id'), fn ($q, $d) => $q->where('dpa_dis_id', (int) $d))
            ->when($request->input('uni_id'), fn ($q, $u) => $q->where('dpa_uni_id', (int) $u))
            ->orderByDesc('dpa_dt_inicio')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($planos, $request, 'Planos de Aula');
        }

        return $this->exportCsv($planos, 'planos_aula');
    }

    private function exportCsv($planos, string $prefix): StreamedResponse
    {
        $filename = "{$prefix}_" . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($planos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Status', 'Professor', 'Escola', 'Turma', 'Série', 'Disciplina', 'Unidade', 'Tema', 'Data Inicial', 'Data Final'], ';');
            foreach ($planos as $p) {
                fputcsv($out, [
                    ucfirst($p->dpa_status ?? ''),
                    $p->funcionario?->fun_nome ?? '',
                    $p->escola?->esc_nome ?? '',
                    $p->turma?->tur_nome ?? '',
                    $p->turma?->serie?->ser_nome ?? '',
                    $p->disciplina?->dis_nome ?? '',
                    $p->unidade ? ($p->unidade->uni_numero . 'º ' . $p->unidade->uni_tipo) : '',
                    $p->dpa_tema,
                    optional($p->dpa_dt_inicio)?->format('d/m/Y') ?? '',
                    optional($p->dpa_dt_fim)?->format('d/m/Y') ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($planos, Request $request, string $titulo): HttpResponse
    {
        $collection = collect($planos);
        $filename   = 'planos_aula_' . now()->format('Ymd_His') . '.pdf';

        $statusCounts = $collection->groupBy('dpa_status')->map->count()->all();

        $filtroParts = [];
        if ($request->input('search')) $filtroParts[] = 'Busca: "' . $request->input('search') . '"';
        if ($request->input('status')) $filtroParts[] = 'Status: ' . ucfirst($request->input('status'));

        $html = view('exports.planos_lista_pdf', [
            'titulo'           => $titulo,
            'planos'           => $collection,
            'total'            => $collection->count(),
            'statusCounts'     => $statusCounts,
            'mostraProfessor'  => false,
            'filtroDescricao'  => implode(' · ', $filtroParts),
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

        $planos = DiarioPlanoAula::query()
            ->where('dpa_fun_id', $funId)
            ->with([
                'turma:tur_id,tur_nome,tur_ser_id',
                'turma.serie:ser_id,ser_nome',
                'disciplina:dis_id,dis_nome',
                'unidade:uni_id,uni_numero,uni_tipo',
                'escola:esc_id,esc_nome',
            ])
            ->when($request->input('search'), function ($q, $s) {
                $q->where('dpa_tema', 'ilike', "%{$s}%");
            })
            ->when($request->input('status'), fn ($q, $st) => $q->where('dpa_status', $st))
            ->when($request->input('tur_id'), fn ($q, $t) => $q->where('dpa_tur_id', (int) $t))
            ->when($request->input('dis_id'), fn ($q, $d) => $q->where('dpa_dis_id', (int) $d))
            ->when($request->input('uni_id'), fn ($q, $u) => $q->where('dpa_uni_id', (int) $u))
            ->orderByDesc('dpa_dt_inicio')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('diario/planos/Index', [
            'planos'  => $planos,
            'filters' => [
                'search'   => $request->input('search', ''),
                'status'   => $request->input('status', ''),
                'tur_id'   => $request->input('tur_id', ''),
                'dis_id'   => $request->input('dis_id', ''),
                'uni_id'   => $request->input('uni_id', ''),
                'per_page' => $perPage,
            ],
            'statuses' => DiarioPlanoAula::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos/Form', [
            'plano'       => null,
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
            'indicadoresSelecionados' => [],
        ]);
    }

    public function store(StorePlanoAulaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['dpa_fun_id'] = (int) $request->user()->fun_id;
        $data['dpa_status'] = DiarioPlanoAula::STATUS_PENDENTE;

        $indicadores = collect($request->input('indicadores', []))->map(fn ($i) => (int) $i)->unique()->all();

        $plano = DB::transaction(function () use ($data, $indicadores) {
            $plano = DiarioPlanoAula::create($data);
            foreach ($indicadores as $indId) {
                DiarioPlanoIndicador::create([
                    'dpi_dpa_id' => $plano->dpa_id,
                    'dpi_ind_id' => $indId,
                ]);
            }
            return $plano;
        });

        return to_route('diario.planos.edit', $plano)->with('success', 'Plano cadastrado com sucesso.');
    }

    public function edit(DiarioPlanoAula $plano, Request $request): Response
    {
        $this->abortIfNotProfessor();
        $this->abortIfNotOwner($plano, $request);

        $plano->load([
            'turma:tur_id,tur_nome,tur_ser_id,tur_esc_id,tur_anl_id',
            'turma.serie:ser_id,ser_nome',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo,uni_dt_inicio,uni_dt_fim,uni_anl_id',
            'escola:esc_id,esc_nome',
            'indicadores',
            'validadoPor:id,name',
        ]);

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos/Form', [
            'plano'       => $plano,
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
            'indicadoresSelecionados' => $plano->indicadores->pluck('dpi_ind_id')->all(),
        ]);
    }

    public function update(StorePlanoAulaRequest $request, DiarioPlanoAula $plano): RedirectResponse
    {
        $this->abortIfNotOwner($plano, $request);

        $data = $request->validated();
        unset($data['indicadores']);

        $indicadores = collect($request->input('indicadores', []))->map(fn ($i) => (int) $i)->unique()->all();

        DB::transaction(function () use ($plano, $data, $indicadores) {
            // Edição volta status p/ pendente se estiver em correção
            if ($plano->dpa_status === DiarioPlanoAula::STATUS_CORRECAO) {
                $data['dpa_status'] = DiarioPlanoAula::STATUS_PENDENTE;
            }

            $plano->update($data);

            DiarioPlanoIndicador::where('dpi_dpa_id', $plano->dpa_id)->delete();
            foreach ($indicadores as $indId) {
                DiarioPlanoIndicador::create([
                    'dpi_dpa_id' => $plano->dpa_id,
                    'dpi_ind_id' => $indId,
                ]);
            }
        });

        return to_route('diario.planos.edit', $plano)->with('success', 'Plano atualizado.');
    }

    public function destroy(DiarioPlanoAula $plano, Request $request): RedirectResponse
    {
        $this->abortIfNotOwner($plano, $request);

        if (! $plano->isPendente()) {
            return back()->with('error', 'Plano só pode ser excluído enquanto estiver pendente.');
        }

        return $this->safeDelete($plano)
            ?? to_route('diario.planos.index')->with('success', 'Plano removido.');
    }

    public function pdf(DiarioPlanoAula $plano, Request $request): HttpResponse
    {
        $this->abortIfNotOwner($plano, $request);

        $plano->load([
            'funcionario:fun_id,fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'turma:tur_id,tur_nome,tur_ser_id',
            'turma.serie:ser_id,ser_nome',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo',
        ]);

        $indicadores = DB::table('edu_diario_plano_indicador as p')
            ->join('edu_serie_indicador as i', 'i.ind_id', '=', 'p.dpi_ind_id')
            ->where('p.dpi_dpa_id', $plano->dpa_id)
            ->orderBy('i.ind_id')
            ->get(['i.ind_id', 'i.ind_descricao']);

        $entidade = DB::table('cfg_parametros_entidade')->value('par_nome_entidade');

        $html = view('exports.diario_plano_aula_pdf', [
            'plano'       => $plano,
            'indicadores' => $indicadores,
            'entidade'    => $entidade,
        ])->render();

        $filename = "plano_aula_{$plano->dpa_id}_" . now()->format('Ymd_His') . '.pdf';

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
        $anlId = (int) $request->input('anl_id');

        $escolas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
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

        $turmas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select('t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 't.tur_ser_id', 'e.esc_nome', 's.ser_nome')
            ->distinct()
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get();

        return response()->json($turmas);
    }

    public function lookupDisciplinas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;
        $turId = (int) $request->input('tur_id');

        $disciplinas = DB::table('edu_turma_professor as tp')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('tp.tup_tur_id', $turId)
            ->whereNull('tp.tup_deleted_at')
            ->select('d.dis_id', 'd.dis_nome')
            ->distinct()
            ->orderBy('d.dis_nome')
            ->get();

        return response()->json($disciplinas);
    }

    public function lookupUnidades(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $anlId = (int) $request->input('anl_id');

        $unidades = Unidade::query()
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim']);

        return response()->json($unidades);
    }

    public function lookupIndicadores(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $anlId = (int) $request->input('anl_id');
        $search = (string) $request->input('search', '');
        $incluirIds = $this->incluirIds($request);

        if (! $turId || ! $disId) {
            return response()->json([]);
        }

        $serId = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');
        if (! $serId) {
            return response()->json([]);
        }

        $query = SerieIndicador::query()
            ->where('ind_ser_id', $serId)
            ->where('ind_dis_id', $disId)
            ->when($search, fn ($q, $s) => $q->whereRaw('UPPER(ind_descricao) LIKE ?', ['%'.mb_strtoupper($s, 'UTF-8').'%']));

        $this->filtroAtivoOuIncluso($query, 'ind_fl_ativo', 'ind_id', $incluirIds);

        return response()->json(
            $query->orderBy('ind_id')->limit(500)->get(['ind_id', 'ind_descricao', 'ind_dis_id', 'ind_fl_ativo'])
        );
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

    private function abortIfNotOwner(DiarioPlanoAula $plano, Request $request): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }
        abort_unless((int) $plano->dpa_fun_id === (int) $request->user()->fun_id, 403);
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
        // Anos letivos das turmas onde o prof é regente (REGULAR)
        $anosVinculados = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->pluck('t.tur_anl_id')
            ->unique();

        if ($anosVinculados->isEmpty()) {
            return [];
        }

        return AnoLetivo::query()
            ->whereIn('anl_id', $anosVinculados->all())
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->toArray();
    }
}
