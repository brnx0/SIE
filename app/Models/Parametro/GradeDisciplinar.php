<?php

namespace App\Models\Parametro;

use App\Models\Disciplina\Disciplina;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeDisciplinar extends Model
{
    protected $table = 'edu_grade_disciplinar';
    protected $primaryKey = 'grd_id';

    const CREATED_AT = 'grd_created_at';
    const UPDATED_AT = 'grd_updated_at';

    protected $fillable = [
        'grd_anl_id',
        'grd_seg_id',
        'grd_ser_id',
        'grd_dis_id',
        'grd_ordem',
        'grd_nome_alternativo',
        'grd_fl_ativo',
    ];

    protected $casts = [
        'grd_anl_id'   => 'integer',
        'grd_seg_id'   => 'integer',
        'grd_ser_id'   => 'integer',
        'grd_dis_id'   => 'integer',
        'grd_ordem'    => 'integer',
        'grd_fl_ativo' => 'boolean',
    ];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'grd_anl_id', 'anl_id');
    }

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'grd_seg_id', 'seg_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'grd_ser_id', 'ser_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'grd_dis_id', 'dis_id');
    }
}
