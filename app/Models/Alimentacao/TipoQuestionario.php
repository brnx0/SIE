<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoQuestionario extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_tipo_questionario';
    protected $primaryKey = 'tpq_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'tpq_created_at';
    const UPDATED_AT = 'tpq_updated_at';

    protected $fillable = ['tpq_nome', 'tpq_ordem', 'tpq_fl_ativo'];

    protected $casts = ['tpq_ordem' => 'integer', 'tpq_fl_ativo' => 'boolean'];

    public function perguntas(): HasMany
    {
        return $this->hasMany(Pergunta::class, 'prg_tpq_id', 'tpq_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('tpq_nome ilike ?', ["%{$q}%"]);
    }
}
