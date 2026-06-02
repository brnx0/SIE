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
    protected $primaryKey = 'tma_id';

    const CREATED_AT = 'tma_created_at';
    const UPDATED_AT = 'tma_updated_at';
    const DELETED_AT = 'tma_deleted_at';

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
        'tma_aln_id',
        'tma_tur_id',
        'tma_anl_id',
        'tma_nr_ordem',
        'tma_tipo_admissao',
        'tma_situacao',
        'tma_dt_matricula',
        'tma_dt_saida',
        'tma_obs',
        'tma_fl_trouxe_transferencia',
        'tma_fl_trouxe_rg',
        'tma_fl_trouxe_reg_nascimento',
        'tma_fl_bolsa_familia',
        'tma_fl_recebe_merenda',
        'tma_fl_usa_transporte',
        'tma_fl_usa_biblioteca',
        'tma_fl_indigena',
        'tma_fl_creche',
        'tma_created_by_id',
    ];

    protected $casts = [
        'tma_aln_id'      => 'integer',
        'tma_tur_id'      => 'integer',
        'tma_anl_id'      => 'integer',
        'tma_nr_ordem'    => 'integer',
        'tma_dt_matricula'           => 'date',
        'tma_dt_saida'               => 'date',
        'tma_fl_trouxe_transferencia' => 'boolean',
        'tma_fl_trouxe_rg'           => 'boolean',
        'tma_fl_trouxe_reg_nascimento' => 'boolean',
        'tma_fl_bolsa_familia'       => 'boolean',
        'tma_fl_recebe_merenda'      => 'boolean',
        'tma_fl_usa_transporte'      => 'boolean',
        'tma_fl_usa_biblioteca'      => 'boolean',
        'tma_fl_indigena'            => 'boolean',
        'tma_fl_creche'              => 'boolean',
        'tma_created_by_id'          => 'integer',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'tma_aln_id', 'aln_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'tma_tur_id', 'tur_id');
    }
}
