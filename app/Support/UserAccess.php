<?php

namespace App\Support;

use App\Models\Escola\Escola;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAccess
{
    /**
     * Roles globais sem lotação: enxergam todos os dados sem filtro de escola.
     * Não possuem vínculo de lotação, então não há escola a filtrar.
     */
    public const ROLES_SEM_FILTRO_LOTACAO = ['admin', 'gestor_secretaria', 'coordenador_interno'];

    /**
     * Usuário que vê tudo sem filtro de lotação (admin e demais roles globais).
     */
    public static function semFiltroLotacao(?User $user): bool
    {
        return (bool) $user && $user->hasAnyRole(self::ROLES_SEM_FILTRO_LOTACAO);
    }

    /**
     * Retorna IDs de escolas em que o usuário tem lotação ativa hoje.
     *
     * Convenção:
     * - Role global sem lotação (admin/gestor_secretaria/coordenador_interno) → null
     *   (sinaliza "sem filtro" para callers; SQL aplica filtro só se array)
     * - Sem fun_id ou sem lotação ativa → [] (caller deve interpretar como acesso negado)
     */
    public static function escolasIds(?User $user): ?array
    {
        if (! $user) return [];
        if (self::semFiltroLotacao($user)) return null;
        if (! $user->fun_id) return [];

        $hoje = now()->toDateString();

        return DB::table('edu_funcionario_lotacao as lot')
            ->join('edu_funcionario_admissao as adm', 'adm.adm_id', '=', 'lot.lot_adm_id')
            ->where('adm.adm_fun_id', $user->fun_id)
            ->where('lot.lot_fl_ativo', true)
            ->where(function ($q) use ($hoje) {
                $q->whereNull('lot.lot_dt_fim')->orWhere('lot.lot_dt_fim', '>=', $hoje);
            })
            ->distinct()
            ->pluck('lot.lot_esc_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Aplica filtro padrão de acesso por escola em uma query.
     *
     * Use no Controller assim:
     *     UserAccess::scopeEscolas($query, auth()->user(), 'tur_esc_id');
     *
     * Comportamento:
     * - Admin → query inalterada
     * - Sem escolas (sem lotação) → força resultado vazio via whereRaw('1=0')
     * - Com escolas → whereIn
     */
    public static function scopeEscolas($query, ?User $user, string $colunaEscola)
    {
        $ids = self::escolasIds($user);
        if ($ids === null) return $query;          // admin
        if (empty($ids)) return $query->whereRaw('1=0');
        return $query->whereIn($colunaEscola, $ids);
    }

    /**
     * Collection de escolas visíveis para o usuário (para popular combos).
     *
     * Admin → todas as ativas.
     * Demais → apenas as das lotações ativas (collection vazia se não tiver).
     */
    public static function escolasVisiveis(?User $user): Collection
    {
        if (! $user) return collect();

        $query = Escola::query()->where('esc_fl_ativo', true)->orderBy('esc_nome');

        if (! self::semFiltroLotacao($user)) {
            $ids = self::escolasIds($user) ?: [-1];
            $query->whereIn('esc_id', $ids);
        }

        return $query->get(['esc_id', 'esc_nome']);
    }

    /**
     * Usuário cujo acesso (a relatórios do diário) é por ESCOLA de lotação,
     * e não por turma de professor. Ex.: secretaria escolar.
     *
     * Admin → false (admin é tratado à parte: enxerga tudo, sem filtro).
     */
    public static function acessoPorEscola(?User $user): bool
    {
        return $user && ! self::semFiltroLotacao($user) && $user->hasAnyRole(['secretaria_escola']);
    }

    /**
     * Escola "padrão" do usuário — primeira lotação ativa. Útil para
     * pré-selecionar combos. Admin → null (sem default).
     */
    public static function escolaDefault(?User $user): ?array
    {
        if (! $user || self::semFiltroLotacao($user)) return null;

        $esc = self::escolasVisiveis($user)->first();
        if (! $esc) return null;

        return ['esc_id' => (int) $esc->esc_id, 'esc_nome' => $esc->esc_nome];
    }
}
