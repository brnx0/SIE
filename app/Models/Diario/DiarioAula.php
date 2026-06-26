<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioAula extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_aula';
    protected $primaryKey = 'aul_id';

    const CREATED_AT = 'aul_created_at';
    const UPDATED_AT = 'aul_updated_at';
    const DELETED_AT = 'aul_deleted_at';

    protected $fillable = [
        'aul_user_id',
        'aul_esc_id',
        'aul_anl_id',
        'aul_tur_id',
        'aul_uni_id',
        'aul_trh_id',
        'aul_dis_id',
        'aul_dt',
        'aul_fl_nao_executada',
        'aul_fl_migrada',
        'aul_origem_tur_id',
        'aul_origem_aul_id',
    ];

    protected $casts = [
        'aul_dt'               => 'date',
        'aul_fl_nao_executada' => 'boolean',
        'aul_fl_migrada'       => 'boolean',
    ];
}
