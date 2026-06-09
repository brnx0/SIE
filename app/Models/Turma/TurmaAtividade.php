<?php

namespace App\Models\Turma;

use App\Models\Parametro\Atividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaAtividade extends Model
{
    protected $table = 'edu_turma_atividade';
    protected $primaryKey = 'tta_id';

    const CREATED_AT = 'tta_created_at';
    const UPDATED_AT = 'tta_updated_at';

    protected $fillable = ['tta_tur_id', 'tta_atv_id'];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'tta_tur_id', 'tur_id');
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class, 'tta_atv_id', 'atv_id');
    }
}
