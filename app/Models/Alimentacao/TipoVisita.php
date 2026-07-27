<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoVisita extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_tipo_visita';
    protected $primaryKey = 'tpv_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tpv_created_at';
    const UPDATED_AT = 'tpv_updated_at';

    protected $fillable = ['tpv_nome', 'tpv_fl_ativo'];

    protected $casts = ['tpv_fl_ativo' => 'boolean'];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('tpv_nome ilike ?', ["%{$q}%"]);
    }
}
