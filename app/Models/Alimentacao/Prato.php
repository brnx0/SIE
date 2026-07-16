<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prato extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'ali_prato';
    protected $primaryKey = 'prt_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'prt_created_at';
    const UPDATED_AT = 'prt_updated_at';
    const DELETED_AT = 'prt_deleted_at';

    protected $fillable = [
        'prt_nome',
        'prt_descricao',
        'prt_modo_preparo',
        'prt_fl_ativo',
        // Tabela nutricional da porção servida — opcional, declarada pelo nutricionista.
        'prt_nut_porcao',
        'prt_nut_porcao_umd_id',
        'prt_nut_energia_kcal',
        'prt_nut_carboidrato_g',
        'prt_nut_acucares_g',
        'prt_nut_proteina_g',
        'prt_nut_gordura_total_g',
        'prt_nut_gordura_saturada_g',
        'prt_nut_gordura_trans_g',
        'prt_nut_fibra_g',
        'prt_nut_sodio_mg',
        'prt_nut_observacao',
    ];

    protected $casts = [
        'prt_fl_ativo'              => 'boolean',
        'prt_nut_porcao'            => 'decimal:2',
        'prt_nut_porcao_umd_id'     => 'integer',
        'prt_nut_energia_kcal'      => 'decimal:2',
        'prt_nut_carboidrato_g'     => 'decimal:2',
        'prt_nut_acucares_g'        => 'decimal:2',
        'prt_nut_proteina_g'        => 'decimal:2',
        'prt_nut_gordura_total_g'   => 'decimal:2',
        'prt_nut_gordura_saturada_g' => 'decimal:2',
        'prt_nut_gordura_trans_g'   => 'decimal:2',
        'prt_nut_fibra_g'           => 'decimal:2',
        'prt_nut_sodio_mg'          => 'decimal:2',
    ];

    /** Unidade da porção da tabela nutricional (ex.: 250 G). */
    public function porcaoUnidadeMedida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'prt_nut_porcao_umd_id', 'umd_id');
    }

    /** True quando o nutricionista preencheu ao menos um valor da tabela. */
    public function temTabelaNutricional(): bool
    {
        return $this->prt_nut_energia_kcal !== null
            || $this->prt_nut_carboidrato_g !== null
            || $this->prt_nut_proteina_g !== null
            || $this->prt_nut_gordura_total_g !== null
            || $this->prt_nut_fibra_g !== null
            || $this->prt_nut_sodio_mg !== null;
    }

    /** Ficha técnica: itens e quantidade por aluno. */
    public function itens(): HasMany
    {
        return $this->hasMany(PratoItem::class, 'pri_prt_id', 'prt_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('prt_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('prt_descricao ilike ?', ["%{$q}%"]);
        });
    }
}
