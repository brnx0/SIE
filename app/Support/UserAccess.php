<?php

namespace App\Support;

use App\Models\Escola\Escola;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAccess
{
    /**
     * Retorna IDs de escolas em que o usuário tem lotação ativa hoje.
     *
     * Convenção:
     * - Admin → null (sinaliza "sem filtro" para callers; SQL aplica filtro só se array)
     * - Sem fun_id ou sem lotação ativa → [] (caller deve interpretar como acesso negado)
     */
    public static function escolasIds(?User $user): ?array
    {
        if (! $user) return [];
        if ($user->isAdmin()) return null;
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

        if (! $user->isAdmin()) {
            $ids = self::escolasIds($user) ?: [-1];
            $query->whereIn('esc_id', $ids);
        }

        return $query->get(['esc_id', 'esc_nome']);
    }

    /**
     * Escola "padrão" do usuário — primeira lotação ativa. Útil para
     * pré-selecionar combos. Admin → null (sem default).
     */
    public static function escolaDefault(?User $user): ?array
    {
        if (! $user || $user->isAdmin()) return null;

        $esc = self::escolasVisiveis($user)->first();
        if (! $esc) return null;

        return ['esc_id' => (int) $esc->esc_id, 'esc_nome' => $esc->esc_nome];
    }
}
