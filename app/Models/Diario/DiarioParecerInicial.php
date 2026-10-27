<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Parecer inicial do aluno — sem unidade letiva (ver migration). */
class DiarioParecerInicial extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_diario_parecer_inicial';
    protected $primaryKey = 'dpr_id';

    const CREATED_AT = 'dpr_created_at';
    const UPDATED_AT = 'dpr_updated_at';
    const DELETED_AT = 'dpr_deleted_at';

    protected $fillable = [
        'dpr_user_id',
        'dpr_esc_id',
        'dpr_anl_id',
        'dpr_tur_id',
        'dpr_dis_id',
        'dpr_aln_id',
        'dpr_descricao',
    ];
}
