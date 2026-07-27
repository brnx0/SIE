<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversaoUnidade extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_conversao_unidade';
    protected $primaryKey = 'cvu_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'cvu_created_at';
    const UPDATED_AT = 'cvu_updated_at';

    protected $fillable = [
        'cvu_itm_id',
        'cvu_umd_origem_id',
        'cvu_umd_destino_id',
        'cvu_fator',
        'cvu_fl_ativo',
    ];

    protected $casts = [
        'cvu_itm_id'         => 'integer',
        'cvu_umd_origem_id'  => 'integer',
        'cvu_umd_destino_id' => 'integer',
        'cvu_fator'          => 'decimal:6',
        'cvu_fl_ativo'       => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'cvu_itm_id', 'itm_id');
    }

    public function unidadeOrigem(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'cvu_umd_origem_id', 'umd_id');
    }

    public function unidadeDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'cvu_umd_destino_id', 'umd_id');
    }
}
