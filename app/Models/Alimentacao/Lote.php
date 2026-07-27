<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de um item. É o que sustenta o relatório de itens próximos do vencimento
 * e a baixa por FEFO. Item não perecível usa lote sem validade.
 */
class Lote extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_lote';
    protected $primaryKey = 'lot_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'lot_created_at';
    const UPDATED_AT = 'lot_updated_at';

    protected $fillable = [
        'lot_itm_id',
        'lot_mrc_id',
        'lot_cd_lote',
        'lot_dt_fabricacao',
        'lot_dt_validade',
    ];

    protected $casts = [
        'lot_itm_id'        => 'integer',
        'lot_mrc_id'        => 'integer',
        'lot_dt_fabricacao' => 'date',
        'lot_dt_validade'   => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'lot_itm_id', 'itm_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'lot_mrc_id', 'mrc_id');
    }

    /** Dias até o vencimento; negativo = já venceu; null = lote sem validade. */
    public function diasParaVencer(): ?int
    {
        return $this->lot_dt_validade
            ? (int) now()->startOfDay()->diffInDays($this->lot_dt_validade->startOfDay(), false)
            : null;
    }

    public function vencido(): bool
    {
        return $this->lot_dt_validade !== null && $this->lot_dt_validade->startOfDay()->isPast();
    }

    /** Ordem FEFO: vence antes sai antes. Lote sem validade vai por último. */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('lot_dt_validade IS NULL')->orderBy('lot_dt_validade')->orderBy('lot_id');
    }
}
