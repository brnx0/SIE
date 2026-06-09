<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    protected $table = 'cfg_atividade';
    protected $primaryKey = 'atv_id';

    const CREATED_AT = 'atv_created_at';
    const UPDATED_AT = 'atv_updated_at';

    protected $fillable = ['atv_descricao', 'atv_fl_ativo'];

    protected $casts = ['atv_fl_ativo' => 'boolean'];
}
