<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;

class CursoFormacaoSuperior extends Model
{
    protected $table = 'edu_curso_formacao_superior';
    protected $primaryKey = 'cfs_id';

    const CREATED_AT = 'cfs_created_at';
    const UPDATED_AT = 'cfs_updated_at';

    public function getRouteKeyName(): string
    {
        return 'cfs_id';
    }

    protected $fillable = [
        'cfs_codigo',
        'cfs_nome',
        'cfs_grau',
        'cfs_area_geral',
        'cfs_area_detalhada',
        'cfs_fl_ativo',
    ];

    protected $casts = [
        'cfs_fl_ativo' => 'boolean',
    ];
}
