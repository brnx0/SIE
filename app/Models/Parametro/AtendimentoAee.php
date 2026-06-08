<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;

class AtendimentoAee extends Model
{
    protected $table = 'cfg_atendimento_aee';
    protected $primaryKey = 'ate_id';

    const CREATED_AT = 'ate_created_at';
    const UPDATED_AT = 'ate_updated_at';

    protected $fillable = ['ate_descricao', 'ate_fl_ativo'];

    protected $casts = ['ate_fl_ativo' => 'boolean'];
}
