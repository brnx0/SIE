<?php

namespace App\Models\Parametro;

use App\Models\Escola\Escola;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaEscola extends Model
{
    protected $table = 'cfg_media_escola';
    protected $primaryKey = 'mde_id';

    const CREATED_AT = 'mde_created_at';
    const UPDATED_AT = 'mde_updated_at';

    protected $fillable = [
        'mde_anl_id',
        'mde_esc_id',
        'mde_media',
        'mde_cnc_id',
        'mde_created_by_id',
        'mde_updated_by_id',
    ];

    protected $casts = [
        'mde_media' => 'decimal:2',
    ];

    public function conceito(): BelongsTo
    {
        return $this->belongsTo(Conceito::class, 'mde_cnc_id', 'cnc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'mde_anl_id', 'anl_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'mde_esc_id', 'esc_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mde_created_by_id');
    }
}
