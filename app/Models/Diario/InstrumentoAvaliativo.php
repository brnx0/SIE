<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstrumentoAvaliativo extends Model
{
    protected $table = 'edu_instrumento_avaliativo';
    protected $primaryKey = 'iav_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'iav_created_at';
    const UPDATED_AT = 'iav_updated_at';

    protected $fillable = [
        'iav_nome',
        'iav_fl_ativo',
        'iav_fl_recuperacao',
    ];

    protected $casts = [
        'iav_fl_ativo'       => 'boolean',
        'iav_fl_recuperacao' => 'boolean',
    ];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('iav_nome ilike ?', ["%{$q}%"]);
    }
}
