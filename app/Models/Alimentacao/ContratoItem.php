<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ContratoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_contrato_item';
    protected $primaryKey = 'cti_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'cti_created_at';
    const UPDATED_AT = 'cti_updated_at';

    protected $fillable = [
        'cti_ctr_id',
        'cti_itm_id',
        'cti_umd_id',
        'cti_qtd',
        'cti_vl_unitario',
    ];

    protected $casts = [
        'cti_ctr_id'      => 'integer',
        'cti_itm_id'      => 'integer',
        'cti_umd_id'      => 'integer',
        'cti_qtd'         => 'decimal:4',
        'cti_vl_unitario' => 'decimal:4',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'cti_ctr_id', 'ctr_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'cti_itm_id', 'itm_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'cti_umd_id', 'umd_id');
    }

    /**
     * Quantidade já pedida em ordens de fornecimento não canceladas.
     * É o que limita novas ordens: não se pode pedir mais do que o contratado.
     */
    public function qtdPedida(): float
    {
        return (float) DB::table('ali_ordem_fornecimento_item as ofi')
            ->join('ali_ordem_fornecimento as ofn', 'ofn.ofn_id', '=', 'ofi.ofi_ofn_id')
            ->where('ofn.ofn_ctr_id', $this->cti_ctr_id)
            ->where('ofi.ofi_itm_id', $this->cti_itm_id)
            ->where('ofn.ofn_situacao', '!=', 'CANCELADA')
            ->whereNull('ofn.ofn_deleted_at')
            ->sum('ofi.ofi_qtd');
    }

    /** Saldo disponível do item no contrato. */
    public function qtdSaldo(): float
    {
        return (float) $this->cti_qtd - $this->qtdPedida();
    }
}
