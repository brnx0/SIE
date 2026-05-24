<?php

namespace App\Models\Escola;

use App\Models\Parametro\AnoLetivo;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EscolaSegmento extends Model
{
    use SoftDeletes;

    protected $table = 'edu_escola_segmento';
    protected $primaryKey = 'esg_id';

    const CREATED_AT = 'esg_created_at';
    const UPDATED_AT = 'esg_updated_at';
    const DELETED_AT = 'esg_deleted_at';

    protected $fillable = [
        'esc_id',
        'seg_id',
        'anl_id_inicio',
        'anl_id_fim',
        'ser_id_inicio',
        'ser_id_fim',
        'esg_fl_ativo',
        'esg_motivo',
    ];

    protected $casts = [
        'esg_fl_ativo' => 'boolean',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'esc_id', 'esc_id');
    }

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'seg_id', 'seg_id');
    }

    public function anoLetivoInicio(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'anl_id_inicio', 'anl_id');
    }

    public function anoLetivoFim(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'anl_id_fim', 'anl_id');
    }

    public function serieInicio(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'ser_id_inicio', 'ser_id');
    }

    public function serieFim(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'ser_id_fim', 'ser_id');
    }

    /**
     * Escopo para buscar segmentos vigentes para um dado ano letivo.
     * Uso nas telas futuras: EscolaSegmento::vigente($anlId)->where('esc_id', $id)->get()
     */
    public function scopeVigente($query, int $anlId)
    {
        return $query
            ->where('anl_id_inicio', '<=', $anlId)
            ->where(fn ($q) => $q->whereNull('anl_id_fim')->orWhere('anl_id_fim', '>=', $anlId));
    }
}
