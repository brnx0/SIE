<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemFornecimentoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_ordem_fornecimento_item';
    protected $primaryKey = 'ofi_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'ofi_created_at';
    const UPDATED_AT = 'ofi_updated_at';

    protected $fillable = [
        'ofi_ofn_id',
        'ofi_itm_id',
        'ofi_umd_id',
        'ofi_qtd',
        'ofi_vl_unitario',
        'ofi_qtd_entregue',
    ];

    protected $casts = [
        'ofi_ofn_id'       => 'integer',
        'ofi_itm_id'       => 'integer',
        'ofi_umd_id'       => 'integer',
        'ofi_qtd'          => 'decimal:4',
        'ofi_vl_unitario'  => 'decimal:4',
        'ofi_qtd_entregue' => 'decimal:4',
    ];

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemFornecimento::class, 'ofi_ofn_id', 'ofn_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'ofi_itm_id', 'itm_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'ofi_umd_id', 'umd_id');
    }

    public function valorTotal(): float
    {
        return (float) $this->ofi_qtd * (float) $this->ofi_vl_unitario;
    }
}
