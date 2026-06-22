<?php

namespace App\Models\Parametro;

use App\Models\Segmento\Segmento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiasLetivos extends Model
{
    protected $table = 'cfg_dias_letivos';
    protected $primaryKey = 'dlt_id';

    const CREATED_AT = 'dlt_created_at';
    const UPDATED_AT = 'dlt_updated_at';

    protected $fillable = [
        'dlt_anl_id',
        'dlt_seg_id',
        'dlt_meses',
        'dlt_periodos',
        'dlt_created_by_id',
        'dlt_updated_by_id',
    ];

    protected $casts = [
        'dlt_meses'    => 'array',
        'dlt_periodos' => 'array',
    ];

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'dlt_seg_id', 'seg_id');
    }
}
