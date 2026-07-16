<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Controle diário da preparação: o que a escola preparou no dia.
 * Confirmar a preparação é o que dá baixa (CONSUMO) no estoque da escola.
 */
class Preparacao extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_RASCUNHO   = 'RASCUNHO';
    public const SITUACAO_CONFIRMADA = 'CONFIRMADA';
    public const SITUACAO_CANCELADA  = 'CANCELADA';
    public const SITUACOES = [self::SITUACAO_RASCUNHO, self::SITUACAO_CONFIRMADA, self::SITUACAO_CANCELADA];

    protected $table = 'ali_preparacao';
    protected $primaryKey = 'prd_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'prd_created_at';
    const UPDATED_AT = 'prd_updated_at';
    const DELETED_AT = 'prd_deleted_at';

    protected $fillable = [
        'prd_esc_id',
        'prd_prt_id',
        'prd_fun_id',
        'prd_usr_id',
        'prd_dt',
        'prd_turno',
        'prd_refeicao',
        'prd_qtd_servida',
        'prd_situacao',
        'prd_obs',
    ];

    protected $casts = [
        'prd_esc_id'      => 'integer',
        'prd_prt_id'      => 'integer',
        'prd_fun_id'      => 'integer',
        'prd_usr_id'      => 'integer',
        'prd_dt'          => 'date',
        'prd_qtd_servida' => 'integer',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'prd_esc_id', 'esc_id');
    }

    public function prato(): BelongsTo
    {
        return $this->belongsTo(Prato::class, 'prd_prt_id', 'prt_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PreparacaoItem::class, 'pdi_prd_id', 'prd_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Funcionario\Funcionario::class, 'prd_fun_id', 'fun_id');
    }

    /** Só rascunho é editável: depois de confirmada, o estoque já foi baixado. */
    public function emRascunho(): bool
    {
        return $this->prd_situacao === self::SITUACAO_RASCUNHO;
    }

    public function scopeConfirmadas(Builder $query): Builder
    {
        return $query->where('prd_situacao', self::SITUACAO_CONFIRMADA);
    }
}
