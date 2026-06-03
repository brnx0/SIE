<?php

namespace App\Models\Turma;

use App\Models\Funcionario\Funcionario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaProfessorApoio extends Model
{
    protected $table = 'edu_turma_professor_apoio';
    protected $primaryKey = 'tpa_id';

    const CREATED_AT = 'tpa_created_at';
    const UPDATED_AT = 'tpa_updated_at';

    protected $fillable = [
        'tpa_tur_id',
        'tpa_fun_id',
        'tpa_obs',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'tpa_tur_id', 'tur_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'tpa_fun_id', 'fun_id');
    }
}
