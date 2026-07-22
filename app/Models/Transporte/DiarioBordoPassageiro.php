<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiarioBordoPassageiro extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'edu_transporte_diario_passageiro';
    protected $primaryKey = 'tdp_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tdp_created_at';
    const UPDATED_AT = 'tdp_updated_at';

    protected $fillable = [
        'tdp_tdb_id',
        'tdp_pas_id',
        'tdp_fl_embarcou',
        'tdp_fl_desembarcou',
        'tdp_hr_embarque',
        'tdp_hr_desembarque',
        'tdp_obs',
    ];

    protected $casts = [
        'tdp_tdb_id'         => 'integer',
        'tdp_pas_id'         => 'integer',
        'tdp_fl_embarcou'    => 'boolean',
        'tdp_fl_desembarcou' => 'boolean',
    ];

    public function diario(): BelongsTo
    {
        return $this->belongsTo(DiarioBordo::class, 'tdp_tdb_id', 'tdb_id');
    }

    public function passageiro(): BelongsTo
    {
        return $this->belongsTo(Passageiro::class, 'tdp_pas_id', 'pas_id');
    }
}
