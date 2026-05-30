<?php

namespace App\Models\Escola;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GerenciaRegional extends Model
{
    use SoftDeletes;

    protected $table = 'edu_gerencia_regional';
    protected $primaryKey = 'ger_id';

    const CREATED_AT = 'ger_created_at';
    const UPDATED_AT = 'ger_updated_at';
    const DELETED_AT = 'ger_deleted_at';

    protected $fillable = [
        'ger_nome',
        'ger_uf',
        'ger_sigla',
        'ger_fl_ativo',
    ];

    protected $casts = [
        'ger_fl_ativo' => 'boolean',
    ];

    public function scopeSearch(Builder $query, ?string $term, ?string $uf = null): Builder
    {
        if ($uf) {
            $query->where('ger_uf', strtoupper($uf));
        }
        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('ger_nome', 'ilike', "%{$term}%")
                    ->orWhere('ger_sigla', 'ilike', "%{$term}%");
            });
        }
        return $query->orderBy('ger_nome');
    }
}
