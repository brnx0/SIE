<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AeeConteudo extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_aee_conteudo';
    protected $primaryKey = 'dac_id';

    const CREATED_AT = 'dac_created_at';
    const UPDATED_AT = 'dac_updated_at';
    const DELETED_AT = 'dac_deleted_at';

    protected $fillable = [
        'dac_user_id',
        'dac_tur_id',
        'dac_uni_id',
        'dac_dt',
        'dac_conteudo',
        'dac_metodologia',
        'dac_fl_plano_executado',
        'dac_dae_id',
    ];

    protected $casts = [
        'dac_dt'                 => 'date',
        'dac_fl_plano_executado' => 'boolean',
    ];
}
