<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotaManual extends Model
{
    use SoftDeletes;

    protected $table = 'edu_nota_manual';
    protected $primaryKey = 'nmn_id';

    const CREATED_AT = 'nmn_created_at';
    const UPDATED_AT = 'nmn_updated_at';
    const DELETED_AT = 'nmn_deleted_at';

    protected $fillable = [
        'nmn_anl_id',
        'nmn_esc_id',
        'nmn_tur_id',
        'nmn_dis_id',
        'nmn_uni_id',
        'nmn_aln_id',
        'nmn_tipo',
        'nmn_media',
        'nmn_cnc_id',
        'nmn_faltas',
        'nmn_user_id',
    ];

    protected $casts = [
        'nmn_media'  => 'decimal:2',
        'nmn_faltas' => 'integer',
    ];
}
