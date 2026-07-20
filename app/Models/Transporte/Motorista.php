<?php

namespace App\Models\Transporte;

use App\Models\Funcionario\Funcionario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Motorista extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const CNH_CATEGORIAS = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'];

    protected $table = 'edu_transporte_motorista';
    protected $primaryKey = 'mot_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'mot_created_at';
    const UPDATED_AT = 'mot_updated_at';
    const DELETED_AT = 'mot_deleted_at';

    protected $fillable = [
        'mot_nome',
        'mot_cpf',
        'mot_dt_nascimento',
        'mot_telefone',
        'mot_fun_id',
        'mot_cnh_numero',
        'mot_cnh_categoria',
        'mot_cnh_dt_validade',
        'mot_fl_ativo',
    ];

    protected $casts = [
        'mot_fun_id'   => 'integer',
        'mot_fl_ativo' => 'boolean',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'mot_fun_id', 'fun_id');
    }

    public function rotas(): HasMany
    {
        return $this->hasMany(Rota::class, 'rot_mot_id', 'mot_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('mot_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('mot_cpf ilike ?', ["%{$q}%"])
               ->orWhereRaw('mot_cnh_numero ilike ?', ["%{$q}%"]);
        });
    }
}
