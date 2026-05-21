<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'edu_municipio';
    protected $primaryKey = 'mun_id';

    const CREATED_AT = 'mun_created_at';
    const UPDATED_AT = 'mun_updated_at';

    protected $fillable = [
        'mun_codigo_ibge',
        'mun_nome',
        'mun_uf',
    ];

    public function scopeSearch(Builder $query, ?string $term, ?string $uf = null): Builder
    {
        if ($uf) {
            $query->where('mun_uf', strtoupper($uf));
        }

        if ($term) {
            // unaccent + ilike → case + accent insensitive (Postgres).
            $query->whereRaw('unaccent(mun_nome) ILIKE unaccent(?)', ["%{$term}%"]);
        }

        return $query->orderBy('mun_nome');
    }
}
