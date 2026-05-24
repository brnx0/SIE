<?php

namespace App\Models\Serie;

use App\Models\Segmento\Segmento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serie extends Model
{
    use SoftDeletes;

    protected $table = 'edu_serie';
    protected $primaryKey = 'ser_id';

    const CREATED_AT = 'ser_created_at';
    const UPDATED_AT = 'ser_updated_at';
    const DELETED_AT = 'ser_deleted_at';

    protected $fillable = [
        'seg_id',
        'ser_cd_referencia',
        'ser_nome',
        'ser_carga_horaria',
        'ser_qt_aulas_semestrais',
        'ser_qt_aulas_anuais',
        'ser_idade',
        'ser_serie_equivalente',
        'ser_nr_ordenacao',
        'ser_ordem_no_segmento',
        'ser_fl_ativo',
    ];

    protected $casts = [
        'ser_carga_horaria'       => 'integer',
        'ser_qt_aulas_semestrais' => 'integer',
        'ser_qt_aulas_anuais'     => 'integer',
        'ser_idade'               => 'integer',
        'ser_nr_ordenacao'        => 'integer',
        'ser_ordem_no_segmento'   => 'integer',
        'ser_fl_ativo'            => 'boolean',
    ];

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'seg_id', 'seg_id');
    }
}
