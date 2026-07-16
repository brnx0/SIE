<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de alteração do contrato: guarda o de/para do que mudou.
 * O valor vigente vive no próprio contrato — aqui é só o histórico.
 */
class ContratoAditivo extends Model
{
    use \App\Models\Concerns\Auditable;

    public const TIPO_VALOR  = 'VALOR';
    public const TIPO_PRAZO  = 'PRAZO';
    public const TIPO_OBJETO = 'OBJETO';
    public const TIPO_QTD    = 'QTD';
    public const TIPOS = [self::TIPO_VALOR, self::TIPO_PRAZO, self::TIPO_OBJETO, self::TIPO_QTD];

    protected $table = 'ali_contrato_aditivo';
    protected $primaryKey = 'adt_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'adt_created_at';
    const UPDATED_AT = 'adt_updated_at';

    protected $fillable = [
        'adt_ctr_id',
        'adt_cti_id',
        'adt_nr_aditivo',
        'adt_tipo',
        'adt_dt_assinatura',
        'adt_vl_anterior',
        'adt_vl_novo',
        'adt_dt_fim_anterior',
        'adt_dt_fim_nova',
        'adt_objeto_anterior',
        'adt_objeto_novo',
        'adt_qtd_anterior',
        'adt_qtd_nova',
        'adt_justificativa',
        'adt_usr_id',
        'adt_fl_cancelado',
        'adt_dt_cancelamento',
        'adt_justificativa_cancelamento',
        'adt_usr_cancelamento_id',
    ];

    protected $casts = [
        'adt_ctr_id'              => 'integer',
        'adt_cti_id'              => 'integer',
        'adt_usr_id'              => 'integer',
        'adt_usr_cancelamento_id' => 'integer',
        'adt_fl_cancelado'        => 'boolean',
        'adt_dt_cancelamento'     => 'datetime',
        'adt_dt_assinatura'   => 'date',
        'adt_dt_fim_anterior' => 'date',
        'adt_dt_fim_nova'     => 'date',
        'adt_vl_anterior'     => 'decimal:2',
        'adt_vl_novo'         => 'decimal:2',
        'adt_qtd_anterior'    => 'decimal:4',
        'adt_qtd_nova'        => 'decimal:4',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'adt_ctr_id', 'ctr_id');
    }

    /** Só em aditivo de QTD: o item cuja quantidade mudou. */
    public function contratoItem(): BelongsTo
    {
        return $this->belongsTo(ContratoItem::class, 'adt_cti_id', 'cti_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'adt_usr_id', 'id');
    }

    public function usuarioCancelamento(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'adt_usr_cancelamento_id', 'id');
    }

    /** Aditivo cancelado não vale mais: o contrato voltou ao valor anterior a ele. */
    public function scopeValidos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('adt_fl_cancelado', false);
    }
}
