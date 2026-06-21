<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AeeFrequencia extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_aee_frequencia';
    protected $primaryKey = 'afr_id';

    const CREATED_AT = 'afr_created_at';
    const UPDATED_AT = 'afr_updated_at';
    const DELETED_AT = 'afr_deleted_at';

    protected $fillable = [
        'afr_user_id',
        'afr_esc_id',
        'afr_anl_id',
        'afr_tur_id',
        'afr_uni_id',
        'afr_aln_id',
        'afr_dt',
        'afr_fl_presente',
    ];

    protected $casts = [
        'afr_dt'          => 'date',
        'afr_fl_presente' => 'boolean',
    ];
}
