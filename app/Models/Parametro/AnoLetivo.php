<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnoLetivo extends Model
{
    use SoftDeletes;

    protected $table = 'cfg_ano_letivo';
    protected $primaryKey = 'anl_id';

    const CREATED_AT = 'anl_created_at';
    const UPDATED_AT = 'anl_updated_at';
    const DELETED_AT = 'anl_deleted_at';

    protected $fillable = [
        'anl_ano',
        'anl_dt_inicio_ano',
        'anl_dt_fim',
        'anl_dt_censo',
        'anl_dt_corte',
        'anl_fl_em_exercicio',
        'anl_fl_progressao_parcial',
        'anl_fl_aprovacao_conselho_freq',
        'anl_frequencia_minima',
        'anl_media_geral',
        'anl_cnc_id_geral',
        'anl_conceito_modo',
        'anl_created_by_id',
        'anl_updated_by_id',
    ];

    protected $casts = [
        'anl_ano' => 'integer',
        'anl_dt_inicio_ano' => 'date',
        'anl_dt_fim' => 'date',
        'anl_dt_censo' => 'date',
        'anl_dt_corte' => 'date',
        'anl_fl_em_exercicio' => 'boolean',
        'anl_fl_progressao_parcial' => 'boolean',
        'anl_fl_aprovacao_conselho_freq' => 'boolean',
        'anl_frequencia_minima' => 'decimal:2',
        'anl_media_geral' => 'decimal:2',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anl_created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anl_updated_by_id');
    }

    /**
     * Filtra anos letivos disponíveis para cadastro/edição.
     * Mostra apenas os "em exercício", mas inclui IDs salvos (edit) mesmo que fora de exercício.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int[]|int|null  $incluirIds  ID(s) de ano letivo já vinculado(s) a manter visível(eis).
     */
    public function scopeParaCadastro($query, $incluirIds = [])
    {
        $ids = collect(is_array($incluirIds) ? $incluirIds : [$incluirIds])
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        return $query->where(function ($q) use ($ids) {
            $q->where('anl_fl_em_exercicio', true);
            if (! empty($ids)) {
                $q->orWhereIn('anl_id', $ids);
            }
        });
    }
}
