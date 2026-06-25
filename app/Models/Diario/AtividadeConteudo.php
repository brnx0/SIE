<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtividadeConteudo extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_atividade_conteudo';
    protected $primaryKey = 'dtc_id';

    const CREATED_AT = 'dtc_created_at';
    const UPDATED_AT = 'dtc_updated_at';
    const DELETED_AT = 'dtc_deleted_at';

    protected $fillable = [
        'dtc_user_id',
        'dtc_tur_id',
        'dtc_uni_id',
        'dtc_dt',
        'dtc_conteudo',
        'dtc_metodologia',
    ];

    protected $casts = [
        'dtc_dt' => 'date',
    ];
}
