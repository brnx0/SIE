<?php

namespace App\Models\Matricula;

use Illuminate\Database\Eloquent\Model;

class TurmaAlunoSituacao extends Model
{
    protected $table      = 'edu_turma_aluno_situacao';
    protected $primaryKey = 'tas_cod';
    public $incrementing  = false;
    public $timestamps    = false;

    protected $casts = [
        'tas_cod'      => 'integer',
        'tas_ctrl_cod' => 'integer',
    ];
}
