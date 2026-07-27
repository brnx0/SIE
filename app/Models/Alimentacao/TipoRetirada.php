<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoRetirada extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_tipo_retirada';
    protected $primaryKey = 'tpr_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tpr_created_at';
    const UPDATED_AT = 'tpr_updated_at';

    protected $fillable = ['tpr_nome', 'tpr_fl_ativo'];

    protected $casts = ['tpr_fl_ativo' => 'boolean'];

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('tpr_nome ilike ?', ["%{$q}%"]);
    }
}
