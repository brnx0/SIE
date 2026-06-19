<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;

class MotivoBaixaFrequencia extends Model
{
    protected $table = 'cfg_motivo_baixa_frequencia';
    protected $primaryKey = 'mbf_id';

    const CREATED_AT = 'mbf_created_at';
    const UPDATED_AT = 'mbf_updated_at';

    protected $fillable = [
        'mbf_descricao',
        'mbf_fl_abona',
        'mbf_fl_ativo',
    ];

    protected $casts = [
        'mbf_fl_abona' => 'boolean',
        'mbf_fl_ativo' => 'boolean',
    ];
}
