<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioEscolaridade extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'edu_funcionario_escolaridade';
    protected $primaryKey = 'fes_id';

    public function getRouteKeyName(): string
    {
        return 'fes_id';
    }

    const CREATED_AT = 'fes_created_at';
    const UPDATED_AT = 'fes_updated_at';

    protected $fillable = [
        'fes_fun_id',
        'fes_nivel',
        'fes_ano_inicio',
        'fes_ano_fim',
        'fes_cfs_id',
        'fes_ies_id',
        'fes_pos_area',
    ];

    protected $casts = [
        'fes_nivel'      => 'integer',
        'fes_ano_inicio' => 'integer',
        'fes_ano_fim'    => 'integer',
        'fes_pos_area'   => 'integer',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fes_fun_id', 'fun_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(CursoFormacaoSuperior::class, 'fes_cfs_id', 'cfs_id');
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(InstituicaoEnsinoSuperior::class, 'fes_ies_id', 'ies_id');
    }
}
