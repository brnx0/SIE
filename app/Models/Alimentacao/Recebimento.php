<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conferência da entrega do fornecedor contra a ordem de fornecimento.
 * Aceitar o recebimento é o que dá entrada no estoque central.
 */
class Recebimento extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const TIPO_ORDINARIO  = 'ORDINARIO';
    public const TIPO_AGRICULTURA = 'AGRICULTURA_FAMILIAR';
    public const TIPOS = [self::TIPO_ORDINARIO, self::TIPO_AGRICULTURA];

    public const SITUACAO_PENDENTE  = 'PENDENTE';
    public const SITUACAO_ACEITO    = 'ACEITO';
    public const SITUACAO_PARCIAL   = 'PARCIAL';
    public const SITUACAO_REJEITADO = 'REJEITADO';
    public const SITUACOES = [
        self::SITUACAO_PENDENTE,
        self::SITUACAO_ACEITO,
        self::SITUACAO_PARCIAL,
        self::SITUACAO_REJEITADO,
    ];

    protected $table = 'ali_recebimento';
    protected $primaryKey = 'rcb_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'rcb_created_at';
    const UPDATED_AT = 'rcb_updated_at';
    const DELETED_AT = 'rcb_deleted_at';

    protected $fillable = [
        'rcb_ofn_id',
        'rcb_fun_id',
        'rcb_usr_id',
        'rcb_dt',
        'rcb_tipo',
        'rcb_situacao',
        'rcb_nr_nota',
        'rcb_obs',
    ];

    protected $casts = [
        'rcb_ofn_id' => 'integer',
        'rcb_fun_id' => 'integer',
        'rcb_usr_id' => 'integer',
        'rcb_dt'     => 'date',
    ];

    public function ordem(): BelongsTo
    {
        return $this->belongsTo(OrdemFornecimento::class, 'rcb_ofn_id', 'ofn_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RecebimentoItem::class, 'rci_rcb_id', 'rcb_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Funcionario\Funcionario::class, 'rcb_fun_id', 'fun_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rcb_usr_id', 'id');
    }

    /**
     * Recebimento ainda em conferência: só ele pode ser editado. Depois de
     * aceito, o estoque já entrou — mexer faria o saldo divergir da conferência.
     */
    public function emConferencia(): bool
    {
        return $this->rcb_situacao === self::SITUACAO_PENDENTE;
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('rcb_situacao', self::SITUACAO_PENDENTE);
    }
}
