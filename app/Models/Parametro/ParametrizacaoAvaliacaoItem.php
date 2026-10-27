<?php

namespace App\Models\Parametro;

use App\Models\Diario\InstrumentoAvaliativo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametrizacaoAvaliacaoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'cfg_parametrizacao_avaliacao_item';
    protected $primaryKey = 'pai_id';

    const CREATED_AT = 'pai_created_at';
    const UPDATED_AT = 'pai_updated_at';

    protected $fillable = [
        'pai_pav_id',
        'pai_iav_id',
        'pai_valor',
    ];

    protected $casts = [
        'pai_valor' => 'decimal:2',
    ];

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(InstrumentoAvaliativo::class, 'pai_iav_id', 'iav_id');
    }
}
