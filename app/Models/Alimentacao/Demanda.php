<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Demanda mensal de merenda da unidade: quanto de cada item vai ser preciso,
 * calculado a partir do cardápio (per capita × alunos × dias letivos).
 */
class Demanda extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_RASCUNHO = 'RASCUNHO';
    public const SITUACAO_FECHADA  = 'FECHADA';
    public const SITUACOES = [self::SITUACAO_RASCUNHO, self::SITUACAO_FECHADA];

    public const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    protected $table = 'ali_demanda';
    protected $primaryKey = 'dmd_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'dmd_created_at';
    const UPDATED_AT = 'dmd_updated_at';
    const DELETED_AT = 'dmd_deleted_at';

    protected $fillable = [
        'dmd_esc_id',
        'dmd_anl_id',
        'dmd_crd_id',
        'dmd_usr_id',
        'dmd_mes',
        'dmd_ano',
        'dmd_dias_letivos',
        'dmd_situacao',
        'dmd_obs',
    ];

    protected $casts = [
        'dmd_esc_id'       => 'integer',
        'dmd_anl_id'       => 'integer',
        'dmd_crd_id'       => 'integer',
        'dmd_usr_id'       => 'integer',
        'dmd_mes'          => 'integer',
        'dmd_ano'          => 'integer',
        'dmd_dias_letivos' => 'integer',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'dmd_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Parametro\AnoLetivo::class, 'dmd_anl_id', 'anl_id');
    }

    public function cardapio(): BelongsTo
    {
        return $this->belongsTo(Cardapio::class, 'dmd_crd_id', 'crd_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(DemandaItem::class, 'dmi_dmd_id', 'dmd_id');
    }

    /** Demanda fechada vira base de compra: recalcular mudaria o que já foi pedido. */
    public function emRascunho(): bool
    {
        return $this->dmd_situacao === self::SITUACAO_RASCUNHO;
    }

    public function nomeMes(): string
    {
        return self::MESES[$this->dmd_mes] ?? '';
    }
}
