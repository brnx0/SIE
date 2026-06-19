<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioConteudo extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_conteudo';
    protected $primaryKey = 'dco_id';

    const CREATED_AT = 'dco_created_at';
    const UPDATED_AT = 'dco_updated_at';
    const DELETED_AT = 'dco_deleted_at';

    protected $fillable = [
        'dco_user_id',
        'dco_tur_id',
        'dco_dis_id',
        'dco_uni_id',
        'dco_dt',
        'dco_conteudo',
        'dco_metodologia',
        'dco_fl_plano_executado',
        'dco_dpa_id',
    ];

    protected $casts = [
        'dco_dt'                 => 'date',
        'dco_fl_plano_executado' => 'boolean',
    ];
}
