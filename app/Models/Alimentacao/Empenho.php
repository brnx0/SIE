<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** Reserva orçamentária que custeia as ordens de fornecimento do contrato. */
class Empenho extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_ATIVO     = 'ATIVO';
    public const SITUACAO_CANCELADO = 'CANCELADO';
    public const SITUACOES = [self::SITUACAO_ATIVO, self::SITUACAO_CANCELADO];

    protected $table = 'ali_empenho';
    protected $primaryKey = 'emp_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'emp_created_at';
    const UPDATED_AT = 'emp_updated_at';
    const DELETED_AT = 'emp_deleted_at';

    protected $fillable = [
        'emp_ctr_id',
        'emp_nr_empenho',
        'emp_dt',
        'emp_vl_empenhado',
        'emp_situacao',
        'emp_obs',
    ];

    protected $casts = [
        'emp_ctr_id'       => 'integer',
        'emp_dt'           => 'date',
        'emp_vl_empenhado' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'emp_ctr_id', 'ctr_id');
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(OrdemFornecimento::class, 'ofn_emp_id', 'emp_id');
    }

    /** Valor já consumido por ordens de fornecimento não canceladas. */
    public function valorConsumido(): float
    {
        return (float) DB::table('ali_ordem_fornecimento_item as ofi')
            ->join('ali_ordem_fornecimento as ofn', 'ofn.ofn_id', '=', 'ofi.ofi_ofn_id')
            ->where('ofn.ofn_emp_id', $this->emp_id)
            ->where('ofn.ofn_situacao', '!=', 'CANCELADA')
            ->whereNull('ofn.ofn_deleted_at')
            ->sum(DB::raw('ofi.ofi_qtd * ofi.ofi_vl_unitario'));
    }

    /** Saldo do empenho: o que ainda pode ser gasto. */
    public function saldo(): float
    {
        return (float) $this->emp_vl_empenhado - $this->valorConsumido();
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('emp_nr_empenho ilike ?', ["%{$q}%"]);
    }
}
