<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cargo extends Model
{
    use SoftDeletes;

    protected $table = 'edu_cargo';
    protected $primaryKey = 'crg_id';

    const CREATED_AT = 'crg_created_at';
    const UPDATED_AT = 'crg_updated_at';
    const DELETED_AT = 'crg_deleted_at';

    protected $fillable = [
        'crg_nome',
        'crg_descricao',
        'crg_fl_ativo',
    ];

    protected $casts = [
        'crg_fl_ativo' => 'boolean',
    ];
}
