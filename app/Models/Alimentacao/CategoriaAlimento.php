<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoriaAlimento extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_categoria_alimento';
    protected $primaryKey = 'cat_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'cat_created_at';
    const UPDATED_AT = 'cat_updated_at';

    protected $fillable = ['cat_nome', 'cat_fl_ativo'];

    protected $casts = ['cat_fl_ativo' => 'boolean'];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('cat_nome ilike ?', ["%{$q}%"]);
    }
}
