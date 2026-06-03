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

    const TAS_ENTRADA_NOVO = 1;


    protected $fillable = [
        'tma_aln_id',
        'tma_tur_id',
        'tma_anl_id',
        'tma_nr_ordem',
        'tma_situacao',
        'tma_tas_cod_entrada',
        'tma_tas_cod_saida',
        'tma_fl_renovado',
        'tma_dt_matricula',
        'tma_dt_saida',
        'tma_obs',
        'tma_created_by_id',
    ];

    protected $casts = [
        'tma_aln_id'          => 'integer',
        'tma_tur_id'          => 'integer',
        'tma_anl_id'          => 'integer',
        'tma_nr_ordem'        => 'integer',
        'tma_tas_cod_entrada'  => 'integer',
        'tma_tas_cod_saida'    => 'integer',
        'tma_fl_renovado'      => 'boolean',
        'tma_dt_matricula'    => 'date',
        'tma_dt_saida'        => 'date',
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

    public function situacaoEntrada(): BelongsTo
    {
        return $this->belongsTo(TurmaAlunoSituacao::class, 'tma_tas_cod_entrada', 'tas_cod');
    }

    public function situacaoSaida(): BelongsTo
    {
        return $this->belongsTo(TurmaAlunoSituacao::class, 'tma_tas_cod_saida', 'tas_cod');
    }
}
