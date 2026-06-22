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
 * Diário de Atividade — seletor de contexto (escola/turma) das turmas de ATIVIDADE.
 * Visível para administrador e professor (monitores usam a role professor).
 * Turmas ATIVIDADE (tur_modalidade='ATIVIDADE', sem disciplina) vinculadas ao
 * funcionário via edu_turma_professor (tup_dis_id NULL). Admin vê todas.
 */
class DiarioAtividadeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortIfNotProfessor();

        return Inertia::render('diario-atividade/Index', [
            'professor'   => $this->resumoProfessor($request->user()),
            'anosLetivos' => $this->anosLetivosAtivos(),
        ]);
    }

    // ============ Endpoints lookup ============

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user  = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');

        if ($user->isAdmin() || ! $funId) {
            return response()->json(
                DB::table('edu_turma as t')
                    ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
                    ->whereNull('t.tur_deleted_at')
                    ->where('t.tur_modalidade', 'ATIVIDADE')
                    ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
                    ->select('e.esc_id', 'e.esc_nome')
                    ->distinct()
                    ->orderBy('e.esc_nome')
                    ->get()
            );
        }

        return response()->json(
            DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_dis_id')
                ->whereNull('tp.tup_deleted_at')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'ATIVIDADE')
                ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
                ->select('e.esc_id', 'e.esc_nome')
                ->distinct()
                ->orderBy('e.esc_nome')
                ->get()
        );
    }

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $user  = $request->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        $base = ($funId && ! $user->isAdmin())
            ? DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_dis_id')
                ->whereNull('tp.tup_deleted_at')
            : DB::table('edu_turma as t');

        $turmas = $base
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'ATIVIDADE')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select(
                't.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 'e.esc_nome',
                't.tur_hora_inicio', 't.tur_hora_fim', 't.tur_aee_sala',
                DB::raw('t.tur_dias_funcionamento::text as tur_dias_funcionamento'),
            )
            ->distinct()
            ->orderBy('t.tur_nome')
            ->get()
            ->map(function ($t) {
                $d = json_decode((string) $t->tur_dias_funcionamento, true);
                $t->tur_dias_funcionamento = is_array($d) ? $d : [];
                $t->tur_hora_inicio = $t->tur_hora_inicio ? substr((string) $t->tur_hora_inicio, 0, 5) : null;
                $t->tur_hora_fim = $t->tur_hora_fim ? substr((string) $t->tur_hora_fim, 0, 5) : null;

                return $t;
            });

        return response()->json($turmas);
    }

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
        abort_unless($user, 403, 'Acesso restrito.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor'), 403, 'Acesso restrito ao professor/monitor.');
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

    private function anosLetivosAtivos(): array
    {
        return AnoLetivo::query()
            ->whereNull('anl_deleted_at')
            ->where('anl_fl_em_exercicio', true)
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->toArray();
    }
}
