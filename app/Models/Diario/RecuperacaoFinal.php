<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recuperação final (anual) por (turma, disciplina, aluno). Vale como Média
 * Final quando maior que a MF obtida antes.
 */
class RecuperacaoFinal extends Model
{
    use SoftDeletes;

    protected $table = 'edu_recuperacao_final';
    protected $primaryKey = 'rcf_id';

    const CREATED_AT = 'rcf_created_at';
    const UPDATED_AT = 'rcf_updated_at';
    const DELETED_AT = 'rcf_deleted_at';

    protected $fillable = [
        'rcf_anl_id',
        'rcf_esc_id',
        'rcf_tur_id',
        'rcf_dis_id',
        'rcf_aln_id',
        'rcf_valor',
        'rcf_user_id',
    ];

    protected $casts = [
        'rcf_anl_id' => 'integer',
        'rcf_esc_id' => 'integer',
        'rcf_tur_id' => 'integer',
        'rcf_dis_id' => 'integer',
        'rcf_aln_id' => 'integer',
        'rcf_valor'  => 'decimal:2',
    ];
}
