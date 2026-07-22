<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Veiculo extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const TIPOS = [
        'onibus'       => 'Ônibus',
        'micro_onibus' => 'Micro-ônibus',
        'van'          => 'Van',
        'kombi'        => 'Kombi',
        'embarcacao'   => 'Embarcação',
        'outro'        => 'Outro',
    ];

    protected $table = 'edu_transporte_veiculo';
    protected $primaryKey = 'vei_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'vei_created_at';
    const UPDATED_AT = 'vei_updated_at';
    const DELETED_AT = 'vei_deleted_at';

    protected $fillable = [
        'vei_placa',
        'vei_renavam',
        'vei_tipo',
        'vei_capacidade',
        'vei_marca',
        'vei_modelo',
        'vei_ano_fabricacao',
        'vei_fl_acessivel',
        'vei_fl_terceirizado',
        'vei_dt_licenciamento',
        'vei_dt_seguro',
        'vei_dt_vistoria',
        'vei_obs',
        'vei_fl_ativo',
    ];

    protected $casts = [
        'vei_capacidade'      => 'integer',
        'vei_ano_fabricacao'  => 'integer',
        'vei_fl_acessivel'    => 'boolean',
        'vei_fl_terceirizado' => 'boolean',
        'vei_fl_ativo'        => 'boolean',
    ];

    public function rotas(): HasMany
    {
        return $this->hasMany(Rota::class, 'rot_vei_id', 'vei_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('vei_placa ilike ?', ["%{$q}%"])
               ->orWhereRaw('vei_marca ilike ?', ["%{$q}%"])
               ->orWhereRaw('vei_modelo ilike ?', ["%{$q}%"])
               ->orWhereRaw('vei_renavam ilike ?', ["%{$q}%"]);
        });
    }
}
