<?php

namespace App\Services\Diario;

use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contexto do Diário de Classe (regular) do professor: anos letivos, escolas,
 * turmas e disciplinas derivados das lotações reais (edu_turma_professor).
 *
 * ESCOPO ESTRITO POR PROFESSOR (fun_id): diferente do diário web (onde secretaria/
 * coordenação "veem tudo"), este service — consumido só pela API v1 do app mobile —
 * SEMPRE filtra pelas lotações do usuário logado. Usuário sem `fun_id` (ex.: admin
 * sem funcionário vinculado) recebe listas vazias, por decisão de produto: o app é
 * a ferramenta do professor, "minhas turmas" = as turmas em que EU leciono.
 *
 * Cobre REGULAR (métodos sem sufixo) e AEE (sufixo `Aee`). Atividade é fase
 * futura. Os dois conjuntos são separados de propósito — ver o bloco AEE no fim.
 */
class ContextoDiarioService
{
    /** Anos letivos com turmas REGULAR vinculadas ao professor (por fun_id). */
    public function anosLetivos(User $user): array
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return [];
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

    /** Escolas com turmas REGULAR do professor no ano (por fun_id). */
    public function escolas(User $user, int $anlId): Collection
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return collect();
        }

        return DB::table('edu_turma_professor as tp')
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
    }

    /** Turmas REGULAR em que o professor está lotado no ano/escola (por fun_id). */
    public function turmas(User $user, int $anlId, int $escId): Collection
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return collect();
        }

        return DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select(
                't.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 't.tur_ser_id',
                't.tur_situacao', 'e.esc_nome', 's.ser_nome',
                DB::raw('s.ser_tipo_avaliacao::text as ser_tipo_avaliacao'),
            )
            ->distinct()
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get()
            ->map(function ($t) {
                $decoded = json_decode((string) $t->ser_tipo_avaliacao, true);
                $t->ser_tipo_avaliacao = is_array($decoded) ? $decoded : [];

                return $t;
            });
    }

    /** Disciplinas que o professor leciona na turma (lotação real, por fun_id). */
    public function disciplinas(User $user, int $turId): Collection
    {
        $funId = (int) $user->fun_id;
        if (! $funId || ! $turId) {
            return collect();
        }

        return DB::table('edu_turma_professor as tp')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('tp.tup_tur_id', $turId)
            ->whereNull('tp.tup_deleted_at')
            ->select('d.dis_id', 'd.dis_nome')
            ->distinct()
            ->orderBy('d.dis_nome')
            ->get();
    }

    /** Unidades (bimestre/trimestre) do ano letivo, com dias de extensão. */
    public function unidades(int $anlId): Collection
    {
        if (! $anlId) {
            return collect();
        }

        return Unidade::query()
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
    }

    // ===================== AEE =====================
    //
    // Métodos separados em vez de um parâmetro `$modalidade` nos de cima: o
    // vínculo AEE não é só "outra modalidade". Ele exige `tup_dis_id IS NULL`,
    // e a turma AEE não tem série — o `leftJoin edu_serie` de `turmas()` traria
    // colunas sempre nulas. Separar mantém cada consulta honesta sobre o que lê.

    /** Regência AEE: vínculo na turma SEM disciplina. Base dos 3 métodos abaixo. */
    private function vinculoAee(int $funId)
    {
        return DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_dis_id')
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'AEE');
    }

    /** Anos letivos com turma AEE atendida pelo professor. */
    public function anosLetivosAee(User $user): array
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return [];
        }

        $anos = $this->vinculoAee($funId)->pluck('t.tur_anl_id')->unique();

        if ($anos->isEmpty()) {
            return [];
        }

        return AnoLetivo::query()
            ->whereIn('anl_id', $anos->all())
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->toArray();
    }

    /** Escolas com turma AEE do professor no ano. */
    public function escolasAee(User $user, int $anlId): Collection
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return collect();
        }

        return $this->vinculoAee($funId)
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->select('e.esc_id', 'e.esc_nome')
            ->distinct()
            ->orderBy('e.esc_nome')
            ->get();
    }

    /**
     * Turmas AEE do professor. Sem série e sem tipo de avaliação — no lugar
     * disso vêm sala, horário e os dias da semana de atendimento, que é o que
     * identifica uma turma AEE para o professor.
     *
     * `atendimentos` e `total_alunos` vêm agregados em duas consultas extras
     * (não uma por turma) para o painel não precisar de round-trips adicionais.
     */
    public function turmasAee(User $user, int $anlId, int $escId): Collection
    {
        $funId = (int) $user->fun_id;
        if (! $funId) {
            return collect();
        }

        $turmas = $this->vinculoAee($funId)
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select(
                't.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id',
                't.tur_situacao', 't.tur_aee_sala', 't.tur_turno',
                't.tur_hora_inicio', 't.tur_hora_fim', 'e.esc_nome',
                DB::raw('t.tur_dias_funcionamento::text as dias'),
            )
            ->distinct()
            ->orderBy('t.tur_nome')
            ->get();

        if ($turmas->isEmpty()) {
            return $turmas;
        }

        $ids = $turmas->pluck('tur_id')->all();

        $atendimentos = DB::table('edu_turma_atendimento_aee as ta')
            ->join('cfg_atendimento_aee as a', 'a.ate_id', '=', 'ta.tat_ate_id')
            ->whereIn('ta.tat_tur_id', $ids)
            ->select('ta.tat_tur_id', 'a.ate_id', 'a.ate_descricao')
            ->orderBy('a.ate_descricao')
            ->get()
            ->groupBy('tat_tur_id');

        $totais = DB::table('edu_turma_aluno')
            ->whereIn('tma_tur_id', $ids)
            ->whereNull('tma_deleted_at')
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->select('tma_tur_id', DB::raw('count(distinct tma_aln_id) as total'))
            ->groupBy('tma_tur_id')
            ->pluck('total', 'tma_tur_id');

        return $turmas->map(function ($t) use ($atendimentos, $totais) {
            $dias = json_decode((string) $t->dias, true);
            $t->dias = is_array($dias) ? array_values($dias) : [];

            // time(0) do Postgres vem como "08:00:00" — o app quer "08:00".
            $t->tur_hora_inicio = $t->tur_hora_inicio ? substr((string) $t->tur_hora_inicio, 0, 5) : null;
            $t->tur_hora_fim = $t->tur_hora_fim ? substr((string) $t->tur_hora_fim, 0, 5) : null;

            $t->atendimentos = ($atendimentos[$t->tur_id] ?? collect())
                ->map(fn ($a) => ['ate_id' => (int) $a->ate_id, 'ate_descricao' => $a->ate_descricao])
                ->values();
            $t->total_alunos = (int) ($totais[$t->tur_id] ?? 0);

            return $t;
        });
    }
}
