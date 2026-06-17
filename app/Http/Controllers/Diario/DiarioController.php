<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diário de Classe — tela seletor de contexto (escola/turma/disciplina) do professor.
 * Concentra o contexto a partir das lotações reais do professor (edu_turma_professor).
 * Só turmas REGULAR. Trava de seleção (length === 1) é feita no frontend.
 */
class DiarioController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        return Inertia::render('diario/Index', [
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosDoProfessor($funId),
        ]);
    }

    // ============ Endpoints lookup ============

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');

        if ($user->isAdmin() || ! $funId) {
            $escolas = DB::table('edu_turma as t')
                ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
                ->select('e.esc_id', 'e.esc_nome')
                ->distinct()
                ->orderBy('e.esc_nome')
                ->get();

            return response()->json($escolas);
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

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        $base = ($funId && ! $user->isAdmin())
            ? DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at')
            : DB::table('edu_turma as t');

        $turmas = $base
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select('t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 't.tur_ser_id', 'e.esc_nome', 's.ser_nome', DB::raw('s.ser_tipo_avaliacao::text as ser_tipo_avaliacao'))
            ->distinct()
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get()
            ->map(function ($t) {
                $decoded = json_decode((string) $t->ser_tipo_avaliacao, true);
                $t->ser_tipo_avaliacao = is_array($decoded) ? $decoded : [];

                return $t;
            });

        return response()->json($turmas);
    }

    public function lookupDisciplinas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user = $request->user();
        $funId = (int) $user->fun_id;
        $turId = (int) $request->input('tur_id');

        if (! $turId) {
            return response()->json([]);
        }

        // Admin não possui lotação (tup): cai no fallback da grade da série.
        if (! $funId || $user->isAdmin()) {
            $serId = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');
            $disciplinas = DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $serId)
                ->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get();

            return response()->json($disciplinas);
        }

        // Professor: somente disciplinas que leciona na turma (lotação real).
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

    /**
     * Unidades (bimestre/trimestre) do ano letivo. O default de "período corrente"
     * (aquele que contém a data de hoje) é calculado no frontend via uni_dt_inicio
     * e uni_dt_fim_efetivo. Inclui uni_dias_extensao p/ o accessor uni_dt_fim_efetivo.
     */
    public function lookupUnidades(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $anlId = (int) $request->input('anl_id');

        if (! $anlId) {
            return response()->json([]);
        }

        return response()->json(
            Unidade::query()
                ->where('uni_anl_id', $anlId)
                ->orderBy('uni_numero')
                ->get(['uni_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao'])
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

    private function anosLetivosDoProfessor(int $funId): array
    {
        if (! $funId || request()->user()->isAdmin()) {
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
