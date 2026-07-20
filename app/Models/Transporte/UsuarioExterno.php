<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsuarioExterno extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_usuario';
    protected $primaryKey = 'tue_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tue_created_at';
    const UPDATED_AT = 'tue_updated_at';
    const DELETED_AT = 'tue_deleted_at';

    protected $fillable = [
        'tue_nome',
        'tue_cpf',
        'tue_dt_nascimento',
        'tue_telefone',
        'tue_tin_id',
        'tue_justificativa',
        'tue_fl_ativo',
    ];

    protected $casts = [
        'tue_tin_id'   => 'integer',
        'tue_fl_ativo' => 'boolean',
    ];

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class, 'tue_tin_id', 'tin_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('tue_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('tue_cpf ilike ?', ["%{$q}%"]);
        });
    }
}
