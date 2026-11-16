<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'cfs_cd_area',
        'cfs_nome',
        'cfs_grau',
        'cfs_ngr_id',
        'cfs_area_geral',
        'cfs_area_detalhada',
        'cfs_fl_ativo',
    ];

    protected $casts = [
        'cfs_fl_ativo' => 'boolean',
        'cfs_ngr_id'   => 'integer',
    ];

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(NivelGraduacao::class, 'cfs_ngr_id', 'ngr_id');
    }

    public function scopeSearch(Builder $q, string $termo): Builder
    {
        return $q->where(function ($w) use ($termo) {
            $w->where('cfs_nome', 'ilike', "%{$termo}%")
                ->orWhere('cfs_codigo', 'ilike', "%{$termo}%")
                ->orWhere('cfs_cd_area', 'ilike', "%{$termo}%")
                ->orWhere('cfs_area_detalhada', 'ilike', "%{$termo}%");
        });
    }
}
