<?php

namespace App\Models\Turma;

use App\Models\Parametro\AtendimentoAee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaAtendimentoAee extends Model
{
    protected $table = 'edu_turma_atendimento_aee';
    protected $primaryKey = 'tat_id';

    const CREATED_AT = 'tat_created_at';
    const UPDATED_AT = 'tat_updated_at';

    protected $fillable = ['tat_tur_id', 'tat_ate_id'];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'tat_tur_id', 'tur_id');
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(AtendimentoAee::class, 'tat_ate_id', 'ate_id');
    }
}
