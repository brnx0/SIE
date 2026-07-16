<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cardapio extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'ali_cardapio';
    protected $primaryKey = 'crd_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'crd_created_at';
    const UPDATED_AT = 'crd_updated_at';
    const DELETED_AT = 'crd_deleted_at';

    protected $fillable = [
        'crd_esc_id',
        'crd_anl_id',
        'crd_nome',
        'crd_dt_inicio',
        'crd_dt_fim',
        'crd_fl_ativo',
    ];

    protected $casts = [
        'crd_esc_id'    => 'integer',
        'crd_anl_id'    => 'integer',
        'crd_dt_inicio' => 'date',
        'crd_dt_fim'    => 'date',
        'crd_fl_ativo'  => 'boolean',
    ];

    /** Nulo = cardápio padrão da rede. */
    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'crd_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Parametro\AnoLetivo::class, 'crd_anl_id', 'anl_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(CardapioDia::class, 'cdi_crd_id', 'crd_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('crd_nome ilike ?', ["%{$q}%"]);
    }

    /**
     * Cardápio vigente da escola numa data: o próprio da escola tem precedência
     * sobre o padrão da rede (`crd_esc_id` nulo).
     */
    public function scopeVigenteParaEscola(Builder $query, int $escId, string $data): Builder
    {
        return $query->where('crd_fl_ativo', true)
            ->whereDate('crd_dt_inicio', '<=', $data)
            ->whereDate('crd_dt_fim', '>=', $data)
            ->where(fn ($w) => $w->where('crd_esc_id', $escId)->orWhereNull('crd_esc_id'))
            ->orderByRaw('crd_esc_id IS NULL'); // escola primeiro, padrão depois
    }
}
