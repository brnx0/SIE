<?php

namespace App\Models\Turma;

use App\Models\Disciplina\Disciplina;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\GradeHorario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TurmaHorario extends Model
{
    use SoftDeletes;

    protected $table = 'edu_turma_horario';
    protected $primaryKey = 'trh_id';

    const CREATED_AT = 'trh_created_at';
    const UPDATED_AT = 'trh_updated_at';
    const DELETED_AT = 'trh_deleted_at';

    protected $fillable = [
        'trh_tur_id',
        'trh_grh_id',
        'trh_dia',
        'trh_fun_id',
        'trh_dis_id',
        'trh_fl_tc',
    ];

    protected $casts = [
        'trh_fl_tc' => 'boolean',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'trh_tur_id', 'tur_id');
    }

    public function gradeHorario(): BelongsTo
    {
        return $this->belongsTo(GradeHorario::class, 'trh_grh_id', 'grh_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'trh_fun_id', 'fun_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'trh_dis_id', 'dis_id');
    }
}
