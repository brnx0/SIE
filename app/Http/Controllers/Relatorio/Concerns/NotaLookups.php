<?php

namespace App\Http\Controllers\Relatorio\Concerns;

use App\Models\Matricula\Matricula;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lookups compartilhados pelos relatórios de notas (boletim, mapa).
 */
trait NotaLookups
{
    public function unidades(Request $request): JsonResponse
    {
        $anlId = (int) $request->input('anl_id');
        if (! $anlId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('cfg_unidade')
                ->where('uni_anl_id', $anlId)
                ->orderBy('uni_numero')
                ->get(['uni_id', 'uni_numero', 'uni_tipo'])
        );
    }

    public function turmas(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        if (! $anlId || ! $escId) {
            return response()->json([]);
        }

        // Acesso por escola → admin (qualquer escola) ou secretaria (só lotação):
        // TODAS as turmas da escola. Demais (professor) → só as que leciona.
        if ($user->isAdmin() || UserAccess::acessoPorEscola($user)) {
            if (! $user->isAdmin()) {
                $ids = UserAccess::escolasIds($user);
                if ($ids !== null && ! in_array($escId, $ids, true)) {
                    return response()->json([]);
                }
            }
            $base = DB::table('edu_turma as t');
        } else {
            $funId = (int) $user->fun_id;
            if (! $funId) {
                return response()->json([]);
            }
            $base = DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at');
        }

        return response()->json(
            $base
                ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->select('t.tur_id', 't.tur_nome', 's.ser_nome')
                ->distinct()
                ->orderBy('s.ser_nome')
                ->orderBy('t.tur_nome')
                ->get()
        );
    }

    /** Disciplinas da grade da série da turma. */
    public function disciplinas(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        $serId = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');
        if (! $serId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $serId)
                ->where('gd.grd_fl_ativo', true)
                ->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get()
        );
    }

    public function alunos(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        return response()->json(
            Matricula::query()
                ->where('tma_tur_id', $turId)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with('aluno:aln_id,aln_nome')
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
                ->values()
                ->map(fn ($m) => ['aln_id' => $m->aluno->aln_id, 'aln_nome' => $m->aluno->aln_nome])
        );
    }

    /** Garante que o usuário pode ver a turma (admin; secretaria por lotação; professor que leciona). */
    protected function autorizarTurma(int $turId): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }

        // Secretaria: autoriza se a escola da turma está na sua lotação.
        if (UserAccess::acessoPorEscola($user)) {
            $escId = (int) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_esc_id');
            $ids = UserAccess::escolasIds($user);
            abort_unless(is_array($ids) && in_array($escId, $ids, true), 403, 'Você não tem lotação na escola desta turma.');

            return;
        }

        $funId = (int) $user->fun_id;
        $ensina = $funId && DB::table('edu_turma_professor')
            ->where('tup_fun_id', $funId)
            ->where('tup_tur_id', $turId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($ensina, 403, 'Você não leciona nesta turma.');
    }

    /** Rótulo da unidade: "1º Bimestre". */
    protected function unidadeLabel(int $numero, string $tipo): string
    {
        return $numero . 'º ' . ucfirst($tipo);
    }

    /** Formata o resultado calculado em texto exibível. */
    protected function celula(array $r): array
    {
        if (($r['tipo'] ?? null) === null) {
            return ['texto' => '—', 'tipo' => null];
        }
        if ($r['tipo'] === 'numerica') {
            return [
                'texto' => $r['valor'] === null ? '—' : number_format((float) $r['valor'], 2, ',', '.'),
                'tipo'  => 'numerica',
            ];
        }

        return ['texto' => $r['conceito']['sigla'] ?? '—', 'tipo' => 'conceitual'];
    }
}
