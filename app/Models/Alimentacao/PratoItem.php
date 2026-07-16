<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PratoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_prato_item';
    protected $primaryKey = 'pri_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'pri_created_at';
    const UPDATED_AT = 'pri_updated_at';

    protected $fillable = [
        'pri_prt_id',
        'pri_itm_id',
        'pri_umd_id',
        'pri_qtd_per_capita',
    ];

    protected $casts = [
        'pri_prt_id'         => 'integer',
        'pri_itm_id'         => 'integer',
        'pri_umd_id'         => 'integer',
        'pri_qtd_per_capita' => 'decimal:4',
    ];

    public function prato(): BelongsTo
    {
        return $this->belongsTo(Prato::class, 'pri_prt_id', 'prt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'pri_itm_id', 'itm_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'pri_umd_id', 'umd_id');
    }
}
