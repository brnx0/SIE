<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelGraduacao extends Model
{
    protected $table = 'edu_nivel_graduacao';
    protected $primaryKey = 'ngr_id';

    const CREATED_AT = 'ngr_created_at';
    const UPDATED_AT = 'ngr_updated_at';

    public function getRouteKeyName(): string
    {
        return 'ngr_id';
    }

    protected $fillable = [
        'ngr_nome',
        'ngr_fl_ativo',
    ];

    protected $casts = [
        'ngr_fl_ativo' => 'boolean',
    ];

    public function cursos(): HasMany
    {
        return $this->hasMany(CursoFormacaoSuperior::class, 'cfs_ngr_id', 'ngr_id');
    }
}
