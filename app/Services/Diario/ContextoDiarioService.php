<?php

namespace App\Services\Diario;

use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\User;
use App\Support\PermissaoTela;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contexto do Diário de Classe (regular) do professor: anos letivos, escolas,
 * turmas, disciplinas e unidades derivados das lotações reais (edu_turma_professor).
 *
 * Fonte única de verdade para a seleção "minhas turmas". Consumido pela API v1
 * (app mobile) via ContextoController. As telas web (DiarioController) ainda têm
 * as queries próprias — consolidar aqui é dívida planejada (ver docs/app-diario-mobile/PLANO.md).
 *
 * Só modalidade REGULAR. AEE/Atividade têm contexto próprio (fases futuras).
 */
class ContextoDiarioService
{
    private const ROTA = '/diario';

    /**
     * "Vê tudo" no diário: admin/gestor global OU perfil só-visualização (nível 2)
     * na tela do Diário (ex.: secretaria). Espelha Controller::veTudoNoDiario.
     */
    private function veTudo(User $user): bool
    {
        return $user->isGestorGlobal()
            || PermissaoTela::nivelParaRota($user, self::ROTA) === PermissaoTela::VISUALIZAR;
    }

    /** Anos letivos com turmas REGULAR vinculadas ao professor (ou todos, se vê tudo). */
    public function anosLetivos(User $user): array
    {
        $funId = (int) $user->fun_id;

        if (! $funId || $user->isAdmin() || $this->veTudo($user)) {
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

    /** Escolas com turmas REGULAR do professor no ano (ou todas, se vê tudo). */
    public function escolas(User $user, int $anlId): Collection
    {
        $funId = (int) $user->fun_id;

        if ($this->veTudo($user) || ! $funId) {
            return DB::table('edu_turma as t')
                ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
                ->select('e.esc_id', 'e.esc_nome')
                ->distinct()
                ->orderBy('e.esc_nome')
                ->get();
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

    /** Turmas REGULAR do professor no ano/escola (ou todas, se vê tudo). */
    public function turmas(User $user, int $anlId, int $escId): Collection
    {
        $funId = (int) $user->fun_id;

        $base = ($funId && ! $this->veTudo($user))
            ? DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at')
            : DB::table('edu_turma as t');

        return $base
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
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

    /**
     * Disciplinas que o professor leciona na turma (lotação real). Admin/sem fun_id
     * cai no fallback da grade da série.
     */
    public function disciplinas(User $user, int $turId): Collection
    {
        if (! $turId) {
            return collect();
        }

        $funId = (int) $user->fun_id;

        if (! $funId || $user->isAdmin()) {
            $serId = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');

            return DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $serId)
                ->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get();
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
}
