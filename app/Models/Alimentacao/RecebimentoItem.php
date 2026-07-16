<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecebimentoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_recebimento_item';
    protected $primaryKey = 'rci_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'rci_created_at';
    const UPDATED_AT = 'rci_updated_at';

    protected $fillable = [
        'rci_rcb_id',
        'rci_ofi_id',
        'rci_itm_id',
        'rci_lot_id',
        'rci_umd_id',
        'rci_qtd_prevista',
        'rci_qtd_recebida',
        'rci_qtd_rejeitada',
        'rci_motivo_rejeicao',
    ];

    protected $casts = [
        'rci_rcb_id'        => 'integer',
        'rci_ofi_id'        => 'integer',
        'rci_itm_id'        => 'integer',
        'rci_lot_id'        => 'integer',
        'rci_umd_id'        => 'integer',
        'rci_qtd_prevista'  => 'decimal:4',
        'rci_qtd_recebida'  => 'decimal:4',
        'rci_qtd_rejeitada' => 'decimal:4',
    ];

    public function recebimento(): BelongsTo
    {
        return $this->belongsTo(Recebimento::class, 'rci_rcb_id', 'rcb_id');
    }

    public function ordemItem(): BelongsTo
    {
        return $this->belongsTo(OrdemFornecimentoItem::class, 'rci_ofi_id', 'ofi_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'rci_itm_id', 'itm_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'rci_lot_id', 'lot_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'rci_umd_id', 'umd_id');
    }

    /** Conferido = alguém já disse quanto chegou (ou que nada chegou). */
    public function conferido(): bool
    {
        return (float) $this->rci_qtd_recebida > 0 || (float) $this->rci_qtd_rejeitada > 0;
    }
}
