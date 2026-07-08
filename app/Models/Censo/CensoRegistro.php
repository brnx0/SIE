<?php

namespace App\Models\Censo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CensoRegistro extends Model
{
    protected $table = 'imp_censo_registro';
    protected $primaryKey = 'icr_id';

    const CREATED_AT = 'icr_created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'icr_icl_id',
        'icr_linha',
        'icr_tipo',
        'icr_campos',
        'icr_dados',
        'icr_status',
        'icr_erro',
    ];

    protected $casts = [
        'icr_campos' => 'array',
        'icr_dados'  => 'array',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CensoLote::class, 'icr_icl_id', 'icl_id');
    }
}
