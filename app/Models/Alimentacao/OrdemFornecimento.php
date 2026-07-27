<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pedido efetivo ao fornecedor, dentro do saldo do contrato e do empenho. */
class OrdemFornecimento extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_EMITIDA   = 'EMITIDA';
    public const SITUACAO_PARCIAL   = 'PARCIAL';
    public const SITUACAO_ENTREGUE  = 'ENTREGUE';
    public const SITUACAO_CANCELADA = 'CANCELADA';
    public const SITUACOES = [
        self::SITUACAO_EMITIDA,
        self::SITUACAO_PARCIAL,
        self::SITUACAO_ENTREGUE,
        self::SITUACAO_CANCELADA,
    ];

    protected $table = 'ali_ordem_fornecimento';
    protected $primaryKey = 'ofn_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'ofn_created_at';
    const UPDATED_AT = 'ofn_updated_at';
    const DELETED_AT = 'ofn_deleted_at';

    protected $fillable = [
        'ofn_ctr_id',
        'ofn_emp_id',
        'ofn_nr_ordem',
        'ofn_dt_emissao',
        'ofn_dt_entrega_prevista',
        'ofn_situacao',
        'ofn_obs',
        'ofn_motivo_cancelamento',
    ];

    protected $casts = [
        'ofn_ctr_id'              => 'integer',
        'ofn_emp_id'              => 'integer',
        'ofn_dt_emissao'          => 'date',
        'ofn_dt_entrega_prevista' => 'date',
    ];

    /**
     * O fornecedor sempre entrega no estoque central — não há escolha de local,
     * então isso não é coluna nem cadastro. O estoque escolar recebe por
     * transferência a partir do central.
     */
    public const LOCAL_ENTREGA = 'Estoque central';

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'ofn_ctr_id', 'ctr_id');
    }

    public function empenho(): BelongsTo
    {
        return $this->belongsTo(Empenho::class, 'ofn_emp_id', 'emp_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemFornecimentoItem::class, 'ofi_ofn_id', 'ofn_id');
    }

    /** Valor total da ordem (soma dos itens). */
    public function valorTotal(): float
    {
        return (float) $this->itens->sum(fn ($i) => (float) $i->ofi_qtd * (float) $i->ofi_vl_unitario);
    }

    /** Ordem cancelada não consome saldo de contrato nem de empenho. */
    public function consomeSaldo(): bool
    {
        return $this->ofn_situacao !== self::SITUACAO_CANCELADA;
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('ofn_nr_ordem ilike ?', ["%{$q}%"]);
    }
}
