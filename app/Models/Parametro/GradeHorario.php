<?php

namespace App\Models\Parametro;

use App\Models\Segmento\Segmento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeHorario extends Model
{
    protected $table = 'edu_grade_horario';
    protected $primaryKey = 'grh_id';

    const CREATED_AT = 'grh_created_at';
    const UPDATED_AT = 'grh_updated_at';

    protected $fillable = [
        'grh_seg_id',
        'grh_turno',
        'grh_hora',
        'grh_ordem',
    ];

    protected $casts = [
        'grh_ordem' => 'integer',
    ];

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'grh_seg_id', 'seg_id');
    }
}
