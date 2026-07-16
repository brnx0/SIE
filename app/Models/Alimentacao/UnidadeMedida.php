<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UnidadeMedida extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_unidade_medida';
    protected $primaryKey = 'umd_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'umd_created_at';
    const UPDATED_AT = 'umd_updated_at';

    protected $fillable = [
        'umd_sigla',
        'umd_nome',
        'umd_fl_fracionavel',
        'umd_fl_ativo',
    ];

    protected $casts = [
        'umd_fl_fracionavel' => 'boolean',
        'umd_fl_ativo'       => 'boolean',
    ];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('umd_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('umd_sigla ilike ?', ["%{$q}%"]);
        });
    }
}
