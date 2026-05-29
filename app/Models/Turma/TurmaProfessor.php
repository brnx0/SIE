<?php

namespace App\Models\Turma;

use App\Models\Disciplina\Disciplina;
use App\Models\Funcionario\Funcionario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TurmaProfessor extends Model
{
    use SoftDeletes;

    protected $table = 'edu_turma_professor';
    protected $primaryKey = 'tup_id';

    const CREATED_AT = 'tup_created_at';
    const UPDATED_AT = 'tup_updated_at';
    const DELETED_AT = 'tup_deleted_at';

    protected $fillable = [
        'tup_tur_id',
        'tup_fun_id',
        'tup_dis_id',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'tup_tur_id', 'tur_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'tup_fun_id', 'fun_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'tup_dis_id', 'dis_id');
    }
}
