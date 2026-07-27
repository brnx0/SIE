<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contrato de fornecimento.
 *
 * As colunas guardam sempre os valores VIGENTES (valor, vigência, objeto): a
 * tela lê direto, sem cálculo. Toda alteração passa por um aditivo, que atualiza
 * o contrato e grava o de/para em `ali_contrato_aditivo` na mesma transação.
 */
class Contrato extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_VIGENTE    = 'VIGENTE';
    public const SITUACAO_SUSPENSO   = 'SUSPENSO';
    public const SITUACAO_ENCERRADO  = 'ENCERRADO';
    public const SITUACAO_RESCINDIDO = 'RESCINDIDO';
    public const SITUACOES = [
        self::SITUACAO_VIGENTE,
        self::SITUACAO_SUSPENSO,
        self::SITUACAO_ENCERRADO,
        self::SITUACAO_RESCINDIDO,
    ];

    protected $table = 'ali_contrato';
    protected $primaryKey = 'ctr_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'ctr_created_at';
    const UPDATED_AT = 'ctr_updated_at';
    const DELETED_AT = 'ctr_deleted_at';

    protected $fillable = [
        'ctr_frn_id',
        'ctr_nr_contrato',
        'ctr_ano',
        'ctr_objeto',
        'ctr_modalidade',
        'ctr_dt_assinatura',
        'ctr_dt_inicio',
        'ctr_dt_fim',
        'ctr_vl_total',
        'ctr_situacao',
    ];

    protected $casts = [
        'ctr_frn_id'        => 'integer',
        'ctr_ano'           => 'integer',
        'ctr_dt_assinatura' => 'date',
        'ctr_dt_inicio'     => 'date',
        'ctr_dt_fim'        => 'date',
        'ctr_vl_total'      => 'decimal:2',
    ];

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'ctr_frn_id', 'frn_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ContratoItem::class, 'cti_ctr_id', 'ctr_id');
    }

    public function aditivos(): HasMany
    {
        return $this->hasMany(ContratoAditivo::class, 'adt_ctr_id', 'ctr_id');
    }

    public function empenhos(): HasMany
    {
        return $this->hasMany(Empenho::class, 'emp_ctr_id', 'ctr_id');
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemFornecimento::class, 'ofn_ctr_id', 'ctr_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(Anexo::class, 'anexavel', 'anx_anexavel_type', 'anx_anexavel_id', 'ctr_id');
    }

    /** Contrato que aceita novas ordens de fornecimento. */
    public function podeEmitirOrdem(): bool
    {
        return $this->ctr_situacao === self::SITUACAO_VIGENTE;
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('ctr_nr_contrato ilike ?', ["%{$q}%"])
               ->orWhereRaw('ctr_objeto ilike ?', ["%{$q}%"])
               ->orWhereHas('fornecedor', fn ($f) => $f->whereRaw('frn_razao_social ilike ?', ["%{$q}%"]));
        });
    }
}
