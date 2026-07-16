<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreparacaoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_preparacao_item';
    protected $primaryKey = 'pdi_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'pdi_created_at';
    const UPDATED_AT = 'pdi_updated_at';

    protected $fillable = [
        'pdi_prd_id',
        'pdi_itm_id',
        'pdi_umd_id',
        'pdi_qtd_prevista',
        'pdi_qtd_utilizada',
    ];

    protected $casts = [
        'pdi_prd_id'        => 'integer',
        'pdi_itm_id'        => 'integer',
        'pdi_umd_id'        => 'integer',
        'pdi_qtd_prevista'  => 'decimal:4',
        'pdi_qtd_utilizada' => 'decimal:4',
    ];

    public function preparacao(): BelongsTo
    {
        return $this->belongsTo(Preparacao::class, 'pdi_prd_id', 'prd_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'pdi_itm_id', 'itm_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'pdi_umd_id', 'umd_id');
    }

    /** Utilizado − previsto. Positivo = gastou mais do que a ficha técnica prevê. */
    public function desvio(): float
    {
        return (float) $this->pdi_qtd_utilizada - (float) $this->pdi_qtd_prevista;
    }
}
