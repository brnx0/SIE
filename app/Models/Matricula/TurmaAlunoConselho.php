<?php

namespace App\Models\Matricula;

use Illuminate\Database\Eloquent\Model;

/**
 * Disciplina aprovada pelo conselho para um aluno na turma (marcada antes do
 * encerramento). Sem soft delete: marcar = inserir, desmarcar = remover.
 */
class TurmaAlunoConselho extends Model
{
    protected $table = 'edu_turma_aluno_conselho';
    protected $primaryKey = 'tac_id';

    const CREATED_AT = 'tac_created_at';
    const UPDATED_AT = 'tac_updated_at';

    protected $fillable = [
        'tac_tur_id',
        'tac_aln_id',
        'tac_dis_id',
        'tac_user_id',
    ];

    protected $casts = [
        'tac_tur_id' => 'integer',
        'tac_aln_id' => 'integer',
        'tac_dis_id' => 'integer',
    ];
}
