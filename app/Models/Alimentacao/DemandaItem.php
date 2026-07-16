<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandaItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_demanda_item';
    protected $primaryKey = 'dmi_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'dmi_created_at';
    const UPDATED_AT = 'dmi_updated_at';

    protected $fillable = [
        'dmi_dmd_id',
        'dmi_itm_id',
        'dmi_umd_id',
        'dmi_qtd_calculada',
        'dmi_qtd_ajustada',
        'dmi_justificativa',
    ];

    protected $casts = [
        'dmi_dmd_id'        => 'integer',
        'dmi_itm_id'        => 'integer',
        'dmi_umd_id'        => 'integer',
        'dmi_qtd_calculada' => 'decimal:4',
        'dmi_qtd_ajustada'  => 'decimal:4',
    ];

    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class, 'dmi_dmd_id', 'dmd_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'dmi_itm_id', 'itm_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'dmi_umd_id', 'umd_id');
    }

    /** O número que vale: o ajuste manual quando existe, senão o calculado. */
    public function qtdFinal(): float
    {
        return $this->dmi_qtd_ajustada !== null
            ? (float) $this->dmi_qtd_ajustada
            : (float) $this->dmi_qtd_calculada;
    }

    public function ajustada(): bool
    {
        return $this->dmi_qtd_ajustada !== null;
    }
}
