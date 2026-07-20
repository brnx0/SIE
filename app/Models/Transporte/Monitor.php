<?php

namespace App\Models\Transporte;

use App\Models\Funcionario\Funcionario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Monitor extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_monitor';
    protected $primaryKey = 'tmo_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tmo_created_at';
    const UPDATED_AT = 'tmo_updated_at';
    const DELETED_AT = 'tmo_deleted_at';

    protected $fillable = [
        'tmo_nome',
        'tmo_cpf',
        'tmo_dt_nascimento',
        'tmo_telefone',
        'tmo_fun_id',
        'tmo_fl_ativo',
    ];

    protected $casts = [
        'tmo_fun_id'   => 'integer',
        'tmo_fl_ativo' => 'boolean',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'tmo_fun_id', 'fun_id');
    }

    public function rotas(): HasMany
    {
        return $this->hasMany(Rota::class, 'rot_tmo_id', 'tmo_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('tmo_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('tmo_cpf ilike ?', ["%{$q}%"]);
        });
    }
}
