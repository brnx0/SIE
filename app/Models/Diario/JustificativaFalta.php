<?php

namespace App\Models\Diario;

use App\Models\Aluno\Aluno;
use App\Models\Parametro\MotivoBaixaFrequencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JustificativaFalta extends Model
{
    use SoftDeletes;

    protected $table = 'edu_justificativa_falta';
    protected $primaryKey = 'jfa_id';

    const CREATED_AT = 'jfa_created_at';
    const UPDATED_AT = 'jfa_updated_at';
    const DELETED_AT = 'jfa_deleted_at';

    protected $fillable = [
        'jfa_anl_id',
        'jfa_esc_id',
        'jfa_tur_id',
        'jfa_aln_id',
        'jfa_mbf_id',
        'jfa_dt_inicio',
        'jfa_dt_fim',
        'jfa_observacao',
        'jfa_user_id',
    ];

    protected $casts = [
        'jfa_dt_inicio' => 'date',
        'jfa_dt_fim'    => 'date',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'jfa_aln_id', 'aln_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoBaixaFrequencia::class, 'jfa_mbf_id', 'mbf_id');
    }
}
