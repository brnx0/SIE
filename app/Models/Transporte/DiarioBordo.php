<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioBordo extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_diario';
    protected $primaryKey = 'tdb_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tdb_created_at';
    const UPDATED_AT = 'tdb_updated_at';
    const DELETED_AT = 'tdb_deleted_at';

    protected $fillable = [
        'tdb_rot_id',
        'tdb_dt_viagem',
        'tdb_vei_id',
        'tdb_mot_id',
        'tdb_tmo_id',
        'tdb_hr_saida',
        'tdb_hr_chegada',
        'tdb_km_inicial',
        'tdb_km_final',
        'tdb_obs',
    ];

    protected $casts = [
        'tdb_rot_id'     => 'integer',
        'tdb_vei_id'     => 'integer',
        'tdb_mot_id'     => 'integer',
        'tdb_tmo_id'     => 'integer',
        'tdb_km_inicial' => 'float',
        'tdb_km_final'   => 'float',
    ];

    public function rota(): BelongsTo
    {
        return $this->belongsTo(Rota::class, 'tdb_rot_id', 'rot_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'tdb_vei_id', 'vei_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'tdb_mot_id', 'mot_id');
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class, 'tdb_tmo_id', 'tmo_id');
    }

    public function passageiros(): HasMany
    {
        return $this->hasMany(DiarioBordoPassageiro::class, 'tdp_tdb_id', 'tdb_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class, 'toc_tdb_id', 'tdb_id');
    }
}
