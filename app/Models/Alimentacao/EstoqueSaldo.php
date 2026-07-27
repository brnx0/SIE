<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo materializado por (estoque, item, lote) — o "saldo em tempo real" das telas.
 *
 * É cache derivado de `ali_movimento`: escrito na mesma transação do movimento,
 * com lock na linha. A fonte da verdade continua sendo o ledger; divergência se
 * apura reconciliando contra ele.
 *
 * `sld_esc_id` nulo = estoque central. Quantidade sempre na unidade base do item.
 */
class EstoqueSaldo extends Model
{
    protected $table = 'ali_estoque_saldo';
    protected $primaryKey = 'sld_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'sld_created_at';
    const UPDATED_AT = 'sld_updated_at';

    protected $fillable = [
        'sld_esc_id',
        'sld_itm_id',
        'sld_lot_id',
        'sld_qtd_base',
        'sld_vl_custo_medio',
    ];

    protected $casts = [
        'sld_esc_id'         => 'integer',
        'sld_itm_id'         => 'integer',
        'sld_lot_id'         => 'integer',
        'sld_qtd_base'       => 'decimal:4',
        'sld_vl_custo_medio' => 'decimal:4',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'sld_esc_id', 'esc_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'sld_itm_id', 'itm_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'sld_lot_id', 'lot_id');
    }

    /** Valor do saldo: quantidade × custo médio. */
    public function valorTotal(): float
    {
        return (float) $this->sld_qtd_base * (float) $this->sld_vl_custo_medio;
    }

    public function scopeDoEstoque(Builder $query, ?int $escId): Builder
    {
        return $escId === null
            ? $query->whereNull('sld_esc_id')
            : $query->where('sld_esc_id', $escId);
    }

    public function scopeComSaldo(Builder $query): Builder
    {
        return $query->where('sld_qtd_base', '>', 0);
    }
}
