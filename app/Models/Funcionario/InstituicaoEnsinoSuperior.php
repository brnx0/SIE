<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;

class InstituicaoEnsinoSuperior extends Model
{
    protected $table = 'edu_instituicao_ensino_superior';
    protected $primaryKey = 'ies_id';

    const CREATED_AT = 'ies_created_at';
    const UPDATED_AT = 'ies_updated_at';

    public function getRouteKeyName(): string
    {
        return 'ies_id';
    }

    protected $fillable = [
        'ies_codigo',
        'ies_nome',
        'ies_categoria',
        'ies_municipio',
        'ies_fl_ativo',
    ];

    protected $casts = [
        'ies_codigo'    => 'integer',
        'ies_categoria' => 'integer',
        'ies_municipio' => 'integer',
        'ies_fl_ativo'  => 'boolean',
    ];
}
