<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_marca';
    protected $primaryKey = 'mrc_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'mrc_created_at';
    const UPDATED_AT = 'mrc_updated_at';

    protected $fillable = ['mrc_nome', 'mrc_fl_ativo'];

    protected $casts = ['mrc_fl_ativo' => 'boolean'];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('mrc_nome ilike ?', ["%{$q}%"]);
    }
}
