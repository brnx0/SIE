<?php

namespace App\Models\Matricula;

use App\Models\Aluno\Aluno;
use App\Models\Turma\Turma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matricula extends Model
{
    use SoftDeletes;

    protected $table = 'edu_turma_aluno';
    protected $primaryKey = 'mat_id';

    const CREATED_AT = 'mat_created_at';
    const UPDATED_AT = 'mat_updated_at';
    const DELETED_AT = 'mat_deleted_at';

    const SITUACAO_ATIVA        = 'ATIVA';
    const SITUACAO_CANCELADA    = 'CANCELADA';
    const SITUACAO_TRANSFERIDA  = 'TRANSFERIDA';
    const SITUACAO_FALECIDO     = 'FALECIDO';
    const SITUACAO_EVADIDO      = 'EVADIDO';

    const TIPO_MATRICULA_NOVA          = 'MATRICULA_NOVA';
    const TIPO_REMATRICULA             = 'REMATRICULA';
    const TIPO_TRANSFERENCIA_INTERNA   = 'TRANSFERENCIA_INTERNA';
    const TIPO_TRANSFERENCIA_EXTERNA   = 'TRANSFERENCIA_EXTERNA';

    protected $fillable = [
        'mat_aln_id',
        'mat_tur_id',
        'mat_anl_id',
        'mat_nr_ordem',
        'mat_tipo_admissao',
        'mat_situacao',
        'mat_dt_matricula',
        'mat_dt_saida',
        'mat_obs',
        'mat_fl_trouxe_transferencia',
        'mat_fl_trouxe_rg',
        'mat_fl_trouxe_reg_nascimento',
        'mat_fl_bolsa_familia',
        'mat_fl_recebe_merenda',
        'mat_fl_usa_transporte',
        'mat_fl_usa_biblioteca',
        'mat_fl_indigena',
        'mat_fl_creche',
        'mat_created_by_id',
    ];

    protected $casts = [
        'mat_aln_id'      => 'integer',
        'mat_tur_id'      => 'integer',
        'mat_anl_id'      => 'integer',
        'mat_nr_ordem'    => 'integer',
        'mat_dt_matricula'           => 'date',
        'mat_dt_saida'               => 'date',
        'mat_fl_trouxe_transferencia' => 'boolean',
        'mat_fl_trouxe_rg'           => 'boolean',
        'mat_fl_trouxe_reg_nascimento' => 'boolean',
        'mat_fl_bolsa_familia'       => 'boolean',
        'mat_fl_recebe_merenda'      => 'boolean',
        'mat_fl_usa_transporte'      => 'boolean',
        'mat_fl_usa_biblioteca'      => 'boolean',
        'mat_fl_indigena'            => 'boolean',
        'mat_fl_creche'              => 'boolean',
        'mat_created_by_id'          => 'integer',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'mat_aln_id', 'aln_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'mat_tur_id', 'tur_id');
    }
}
