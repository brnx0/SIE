<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioAvaliacaoDescritiva extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_avaliacao_descritiva';
    protected $primaryKey = 'dad_id';

    const CREATED_AT = 'dad_created_at';
    const UPDATED_AT = 'dad_updated_at';
    const DELETED_AT = 'dad_deleted_at';

    protected $fillable = [
        'dad_user_id',
        'dad_esc_id',
        'dad_anl_id',
        'dad_tur_id',
        'dad_dis_id',
        'dad_uni_id',
        'dad_aln_id',
        'dad_descricao',
    ];
}
