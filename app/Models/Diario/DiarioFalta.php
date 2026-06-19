<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioFalta extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_falta';
    protected $primaryKey = 'fal_id';

    const CREATED_AT = 'fal_created_at';
    const UPDATED_AT = 'fal_updated_at';
    const DELETED_AT = 'fal_deleted_at';

    protected $fillable = [
        'fal_aul_id',
        'fal_aln_id',
        'fal_fl_presente',
    ];

    protected $casts = [
        'fal_fl_presente' => 'boolean',
    ];
}
