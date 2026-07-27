<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoItem extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_tipo_item';
    protected $primaryKey = 'tpi_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tpi_created_at';
    const UPDATED_AT = 'tpi_updated_at';

    protected $fillable = ['tpi_nome', 'tpi_fl_ativo'];

    protected $casts = ['tpi_fl_ativo' => 'boolean'];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('tpi_nome ilike ?', ["%{$q}%"]);
    }
}
