<?php

namespace App\Models\Segmento;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Segmento extends Model
{
    use SoftDeletes;

    protected $table = 'edu_segmento';
    protected $primaryKey = 'seg_id';

    const CREATED_AT = 'seg_created_at';
    const UPDATED_AT = 'seg_updated_at';

    const DELETED_AT = 'seg_deleted_at';

    protected $fillable = [
        'seg_cd_inep',
        'seg_nome_reduzido',
        'seg_nome_completo',
        'seg_qt_anos_escolares',
        'seg_ordem',
        'seg_dt_abertura',
        'seg_dt_encerramento',
        'seg_fl_prereq',
        'seg_ds_prereq',
        'seg_observacoes',
        'seg_fl_ativo',
    ];

    protected $casts = [
        'seg_qt_anos_escolares' => 'integer',
        'seg_ordem' => 'integer',
        'seg_fl_prereq' => 'boolean',
        'seg_fl_ativo' => 'boolean',
    ];
}
