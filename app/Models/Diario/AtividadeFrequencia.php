<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtividadeFrequencia extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_atividade_frequencia';
    protected $primaryKey = 'atf_id';

    const CREATED_AT = 'atf_created_at';
    const UPDATED_AT = 'atf_updated_at';
    const DELETED_AT = 'atf_deleted_at';

    protected $fillable = [
        'atf_user_id',
        'atf_esc_id',
        'atf_anl_id',
        'atf_tur_id',
        'atf_uni_id',
        'atf_aln_id',
        'atf_dt',
        'atf_fl_presente',
    ];

    protected $casts = [
        'atf_dt'          => 'date',
        'atf_fl_presente' => 'boolean',
    ];
}
