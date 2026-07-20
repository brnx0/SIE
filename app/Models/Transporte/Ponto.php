<?php

namespace App\Models\Transporte;

use App\Models\Escola\Bairro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ponto extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_ponto';
    protected $primaryKey = 'pto_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'pto_created_at';
    const UPDATED_AT = 'pto_updated_at';
    const DELETED_AT = 'pto_deleted_at';

    protected $fillable = [
        'pto_rot_id',
        'pto_ordem',
        'pto_nome',
        'pto_endereco',
        'pto_bai_id',
        'pto_latitude',
        'pto_longitude',
        'pto_hr_prevista',
        'pto_tin_id',
    ];

    protected $casts = [
        'pto_rot_id'    => 'integer',
        'pto_ordem'     => 'integer',
        'pto_bai_id'    => 'integer',
        'pto_latitude'  => 'float',
        'pto_longitude' => 'float',
        'pto_tin_id'    => 'integer',
    ];

    public function rota(): BelongsTo
    {
        return $this->belongsTo(Rota::class, 'pto_rot_id', 'rot_id');
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class, 'pto_bai_id', 'bai_id');
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'pto_tin_id', 'tin_id');
    }

    public function passageiros(): HasMany
    {
        return $this->hasMany(Passageiro::class, 'pas_pto_id', 'pto_id');
    }
}
