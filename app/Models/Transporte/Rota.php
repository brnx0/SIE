<?php

namespace App\Models\Transporte;

use App\Models\Parametro\AnoLetivo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rota extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_rota';
    protected $primaryKey = 'rot_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'rot_created_at';
    const UPDATED_AT = 'rot_updated_at';
    const DELETED_AT = 'rot_deleted_at';

    protected $fillable = [
        'rot_anl_id',
        'rot_nome',
        'rot_turno',
        'rot_zona',
        'rot_vei_id',
        'rot_mot_id',
        'rot_tmo_id',
        'rot_km',
        'rot_tempo_min',
        'rot_trajeto',
        'rot_obs',
        'rot_fl_ativo',
    ];

    protected $casts = [
        'rot_anl_id'    => 'integer',
        'rot_vei_id'    => 'integer',
        'rot_mot_id'    => 'integer',
        'rot_tmo_id'    => 'integer',
        'rot_km'        => 'float',
        'rot_tempo_min' => 'integer',
        'rot_trajeto'   => 'array',
        'rot_fl_ativo'  => 'boolean',
    ];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'rot_anl_id', 'anl_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'rot_vei_id', 'vei_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'rot_mot_id', 'mot_id');
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class, 'rot_tmo_id', 'tmo_id');
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(Ponto::class, 'pto_rot_id', 'rot_id')->orderBy('pto_ordem');
    }

    public function passageiros(): HasMany
    {
        return $this->hasMany(Passageiro::class, 'pas_rot_id', 'rot_id');
    }

    public function diarios(): HasMany
    {
        return $this->hasMany(DiarioBordo::class, 'tdb_rot_id', 'rot_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('rot_nome ilike ?', ["%{$q}%"]);
    }
}
