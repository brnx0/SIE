<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Razão (ledger) do estoque — append-only.
 *
 * Não tem update nem soft delete de propósito: o saldo é derivado daqui, então
 * reescrever uma linha faria o histórico contar uma versão diferente do saldo
 * atual. Erro se corrige com ESTORNO apontando para o movimento original.
 *
 * `mov_esc_id` nulo = estoque central; preenchido = estoque daquela escola.
 */
class Movimento extends Model
{
    use \App\Models\Concerns\Auditable;

    public const TIPO_ENTRADA       = 'ENTRADA';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';
    public const TIPO_RETIRADA      = 'RETIRADA';
    public const TIPO_CONSUMO       = 'CONSUMO';
    public const TIPO_AJUSTE        = 'AJUSTE';
    public const TIPO_ESTORNO       = 'ESTORNO';

    public const TIPOS = [
        self::TIPO_ENTRADA,
        self::TIPO_TRANSFERENCIA,
        self::TIPO_RETIRADA,
        self::TIPO_CONSUMO,
        self::TIPO_AJUSTE,
        self::TIPO_ESTORNO,
    ];

    /** Tipos que aumentam o saldo. Os demais diminuem. */
    public const TIPOS_ENTRADA = [self::TIPO_ENTRADA];

    protected $table = 'ali_movimento';
    protected $primaryKey = 'mov_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'mov_created_at';
    const UPDATED_AT = 'mov_updated_at';

    protected $fillable = [
        'mov_esc_id',
        'mov_itm_id',
        'mov_lot_id',
        'mov_umd_id',
        'mov_frn_id',
        'mov_ofn_id',
        'mov_tpr_id',
        'mov_fun_id',
        'mov_responsavel',
        'mov_usr_id',
        'mov_tipo',
        'mov_qtd',
        'mov_qtd_base',
        'mov_vl_unitario',
        'mov_vl_total',
        'mov_dt',
        'mov_trf_par_id',
        'mov_estorno_de_id',
        'mov_origem',
        'mov_obs',
    ];

    protected $casts = [
        'mov_esc_id'        => 'integer',
        'mov_itm_id'        => 'integer',
        'mov_lot_id'        => 'integer',
        'mov_umd_id'        => 'integer',
        'mov_frn_id'        => 'integer',
        'mov_ofn_id'        => 'integer',
        'mov_tpr_id'        => 'integer',
        'mov_fun_id'        => 'integer',
        'mov_usr_id'        => 'integer',
        'mov_trf_par_id'    => 'integer',
        'mov_estorno_de_id' => 'integer',
        'mov_qtd'           => 'decimal:4',
        'mov_qtd_base'      => 'decimal:4',
        'mov_vl_unitario'   => 'decimal:4',
        'mov_vl_total'      => 'decimal:2',
        'mov_dt'            => 'date',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'mov_esc_id', 'esc_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'mov_itm_id', 'itm_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'mov_lot_id', 'lot_id');
    }

    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'mov_umd_id', 'umd_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'mov_frn_id', 'frn_id');
    }

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemFornecimento::class, 'mov_ofn_id', 'ofn_id');
    }

    public function tipoRetirada(): BelongsTo
    {
        return $this->belongsTo(TipoRetirada::class, 'mov_tpr_id', 'tpr_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Funcionario\Funcionario::class, 'mov_fun_id', 'fun_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'mov_usr_id', 'id');
    }

    /** O outro lado da transferência (SAIDA ↔ ENTRADA). */
    public function parTransferencia(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mov_trf_par_id', 'mov_id');
    }

    public function estornoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mov_estorno_de_id', 'mov_id');
    }

    /** +1 aumenta o saldo, -1 diminui. */
    public function sinal(): int
    {
        return in_array($this->mov_tipo, self::TIPOS_ENTRADA, true) ? 1 : -1;
    }

    public function scopeDoEstoque(Builder $query, ?int $escId): Builder
    {
        return $escId === null
            ? $query->whereNull('mov_esc_id')
            : $query->where('mov_esc_id', $escId);
    }

    public function scopeCentral(Builder $query): Builder
    {
        return $query->whereNull('mov_esc_id');
    }
}
