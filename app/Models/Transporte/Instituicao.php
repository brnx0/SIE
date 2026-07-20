<?php

namespace App\Models\Transporte;

use App\Models\Escola\Bairro;
use App\Models\Escola\Escola;
use App\Models\Municipio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instituicao extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const ESFERAS = [
        'municipal' => 'Municipal',
        'estadual'  => 'Estadual',
        'federal'   => 'Federal',
        'privada'   => 'Privada',
        'outra'     => 'Outra',
    ];

    protected $table = 'edu_transporte_instituicao';
    protected $primaryKey = 'tin_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tin_created_at';
    const UPDATED_AT = 'tin_updated_at';
    const DELETED_AT = 'tin_deleted_at';

    protected $fillable = [
        'tin_nome',
        'tin_esc_id',
        'tin_esfera',
        'tin_endereco',
        'tin_bai_id',
        'tin_mun_id',
        'tin_telefone',
        'tin_latitude',
        'tin_longitude',
        'tin_fl_ativo',
    ];

    protected $casts = [
        'tin_esc_id'    => 'integer',
        'tin_bai_id'    => 'integer',
        'tin_mun_id'    => 'integer',
        'tin_latitude'  => 'float',
        'tin_longitude' => 'float',
        'tin_fl_ativo'  => 'boolean',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'tin_esc_id', 'esc_id');
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class, 'tin_bai_id', 'bai_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'tin_mun_id', 'mun_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('tin_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('tin_endereco ilike ?', ["%{$q}%"]);
        });
    }
}
