<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\StorePlanoAulaRequest;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Diario\DiarioPlanoIndicador;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\Serie\SerieIndicador;
use App\Support\UserAccess;
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

        $user = $request->user();

        $planos = DiarioPlanoAula::query()
            ->when(! $user->isAdmin() && ! $this->soVisualizacaoTela($request), fn ($q) => $q->where('dpa_user_id', (int) $user->id))
            ->with([
                'serie:ser_id,ser_nome',
                'turma:tur_id,tur_nome',
                'disciplina:dis_id,dis_nome',
                'unidade:uni_id,uni_numero,uni_tipo',
                'escola:esc_id,esc_nome',
                'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
            ])
            ->when($request->input('search'), fn ($q, $s) => $q->where('dpa_tema', 'ilike', "%{$s}%"))
            ->when($request->input('status'), fn ($q, $st) => $q->where('dpa_status', $st))
            ->when($request->input('ser_id'), fn ($q, $s) => $q->where('dpa_ser_id', (int) $s))
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
            fputcsv($out, ['Status', 'Professor', 'Escola', 'Série', 'Turma', 'Disciplina', 'Unidade', 'Tema', 'Data Inicial', 'Data Final'], ';');
            foreach ($planos as $p) {
                fputcsv($out, [
                    ucfirst($p->dpa_status ?? ''),
                    $p->funcionario?->fun_nome ?? '',
                    $p->escola?->esc_nome ?? '',
                    $p->serie?->ser_nome ?? '',
                    $p->turma?->tur_nome ?? 'Todas',
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

        $user = $request->user();

        $planos = DiarioPlanoAula::query()
            ->when(! $user->isAdmin() && ! $this->soVisualizacaoTela($request), fn ($q) => $q->where('dpa_user_id', (int) $user->id))
            ->with([
                'serie:ser_id,ser_nome',
                'turma:tur_id,tur_nome',
                'disciplina:dis_id,dis_nome',
                'unidade:uni_id,uni_numero,uni_tipo',
                'escola:esc_id,esc_nome',
            ])
            ->when($request->input('search'), function ($q, $s) {
                $q->where('dpa_tema', 'ilike', "%{$s}%");
            })
            ->when($request->input('status'), fn ($q, $st) => $q->where('dpa_status', $st))
            ->when($request->input('ser_id'), fn ($q, $s) => $q->where('dpa_ser_id', (int) $s))
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
                'ser_id'   => $request->input('ser_id', ''),
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
        // O middleware `tela` deixa passar todo GET, então sem isto um perfil de
        // só-visualização abre o formulário pela URL e só descobre no salvar.
        abort_if($this->soVisualizacaoTela($request), 403, 'Seu perfil não pode criar planos de aula.');

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos/Form', [
            'plano'       => null,
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
            'indicadoresSelecionados' => [],
            'podeEditar'  => true,
        ]);
    }

    public function store(StorePlanoAulaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['dpa_user_id'] = (int) $request->user()->id;
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

    public function edit(DiarioPlanoAula $plano, Request $request): Response|RedirectResponse
    {
        $this->abortIfNotProfessor();
        if ($r = $this->redirectSeNaoDono($plano, $request)) return $r;

        $plano->load([
            'serie:ser_id,ser_nome',
            'turma:tur_id,tur_nome',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo,uni_dt_inicio,uni_dt_fim,uni_anl_id',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'indicadores',
            'validadoPor:id,name',
            'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
            'usuario:id,name',
        ]);

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/planos/Form', [
            'plano'       => $plano,
            // Professor responsável = AUTOR do plano (dpa_user_id), não o usuário
            // logado — senão um admin/coordenador vendo o plano apareceria no lugar.
            'professor'   => $this->resumoProfessorDoPlano($plano),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
            'indicadoresSelecionados' => $plano->indicadores->pluck('dpi_ind_id')->all(),
            'podeEditar'  => $request->user()->isAdmin()
                || (int) $plano->dpa_user_id === (int) $request->user()->id,
        ]);
    }

    public function update(StorePlanoAulaRequest $request, DiarioPlanoAula $plano): RedirectResponse
    {
        if ($r = $this->redirectSeNaoDono($plano, $request)) return $r;

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
        if ($r = $this->redirectSeNaoDono($plano, $request)) return $r;

        if (! $plano->isPendente()) {
            return back()->with('error', 'Plano só pode ser excluído enquanto estiver pendente.');
        }

        return $this->safeDelete($plano)
            ?? to_route('diario.planos.index')->with('success', 'Plano removido.');
    }

    public function pdf(DiarioPlanoAula $plano, Request $request): HttpResponse|RedirectResponse
    {
        if ($r = $this->redirectSeNaoDono($plano, $request)) return $r;

        $plano->load([
            'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'serie:ser_id,ser_nome',
            'turma:tur_id,tur_nome',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo',
            'validadoPor:id,name',
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

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');

        // Gestor global (admin/gestor_secretaria) supervisiona toda a rede: lista
        // TODAS as escolas ativas, e não apenas as que já têm turma no ano.
        if ($user->isGestorGlobal() || ! $funId) {
            return response()->json(UserAccess::escolasVisiveis($user));
        }

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

    /** Séries do professor no ano/escola (baseadas nas turmas em que ele leciona). */
    public function lookupSeries(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        $base = ($funId && ! $user->isGestorGlobal())
            ? DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at')
            : DB::table('edu_turma as t');

        $series = $base
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            // ser_fl_multi: informativo (a turma do plano é opcional em qualquer série).
            ->select('s.ser_id', 's.ser_nome', 's.ser_fl_multi')
            ->distinct()
            ->orderBy('s.ser_nome')
            ->get()
            ->map(fn ($s) => [
                'ser_id'       => (int) $s->ser_id,
                'ser_nome'     => $s->ser_nome,
                'ser_fl_multi' => (bool) $s->ser_fl_multi,
            ]);

        return response()->json($series);
    }

    /**
     * Turmas REGULARES da série (escola/ano) em que o professor tem regência.
     * O plano segue por série e a turma
     * é um recorte OPCIONAL.
     */
    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user  = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');
        $serId = (int) $request->input('ser_id');

        if (! $serId) {
            return response()->json([]);
        }

        $base = ($funId && ! $user->isGestorGlobal())
            ? DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at')
            : DB::table('edu_turma as t');

        $turmas = $base
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_ser_id', $serId)
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select('t.tur_id', 't.tur_nome')
            ->distinct()
            ->orderBy('t.tur_nome')
            ->get()
            ->map(fn ($t) => ['tur_id' => (int) $t->tur_id, 'tur_nome' => $t->tur_nome]);

        return response()->json($turmas);
    }

    /** Disciplinas que o professor leciona em turmas da série (ou toda a grade, se admin). */
    public function lookupDisciplinas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $serId = (int) $request->input('ser_id');
        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');

        if (! $serId) {
            return response()->json([]);
        }

        if (! $funId || $user->isGestorGlobal()) {
            $disciplinas = DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $serId)
                ->where('gd.grd_fl_ativo', true)
                ->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get();

            return response()->json($disciplinas);
        }

        $disciplinas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('t.tur_ser_id', $serId)
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
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

        $serId = (int) $request->input('ser_id');
        $disId = (int) $request->input('dis_id');
        $search = (string) $request->input('search', '');
        $incluirIds = $this->incluirIds($request);

        if (! $serId || ! $disId) {
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
        abort_unless(request()->user(), 403);
    }

    /**
     * Retorna um redirect com flash (toast amigável no front) quando o usuário
     * não pode operar o plano; ou `null` quando pode. Sem exceção/página crua.
     */
    private function redirectSeNaoDono(DiarioPlanoAula $plano, Request $request): ?RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return null;
        }
        // Só-visualização (nível 2) pode ABRIR o plano de qualquer um (leitura/GET),
        // mas NÃO pode gravar. Em escrita (PUT/DELETE), só o dono.
        if ($request->isMethod('GET') && $this->soVisualizacaoTela($request)) {
            return null;
        }
        if ((int) $plano->dpa_user_id !== (int) $request->user()->id) {
            return redirect()->route('diario.planos.index')
                ->with('error', 'Você só pode editar planos de aula que você mesmo criou.');
        }

        return null;
    }

    private function resumoProfessor($user): array
    {
        if (! $user->fun_id) {
            return ['fun_id' => null, 'fun_nome' => $user->name];
        }
        $fun = DB::table('edu_funcionario')->where('fun_id', $user->fun_id)->first(['fun_id', 'fun_nome']);
        return [
            'fun_id'   => $fun?->fun_id,
            'fun_nome' => $fun?->fun_nome ?? $user->name,
        ];
    }

    /**
     * Professor responsável pelo plano = seu AUTOR (dpa_user_id), derivado via
     * users.fun_id. Cai no nome do usuário se ele não tiver funcionário vinculado.
     * Requer o plano com 'funcionario' e 'usuario' carregados.
     */
    private function resumoProfessorDoPlano(DiarioPlanoAula $plano): array
    {
        return [
            'fun_id'   => $plano->funcionario?->fun_id,
            'fun_nome' => $plano->funcionario?->fun_nome ?? $plano->usuario?->name ?? '—',
        ];
    }

    private function anosLetivosDoProfessor(int $funId): array
    {
        if (! $funId || request()->user()->isGestorGlobal()) {
            return AnoLetivo::query()
                ->whereNull('anl_deleted_at')
                ->orderByDesc('anl_ano')
                ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
                ->toArray();
        }

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
