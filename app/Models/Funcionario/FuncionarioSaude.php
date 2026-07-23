<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioSaude extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'edu_funcionario_saude';
    protected $primaryKey = 'fus_id';

    const CREATED_AT = 'fus_created_at';
    const UPDATED_AT = 'fus_updated_at';

    protected $fillable = [
        'fus_fun_id',
        'fus_fl_pcd',
        'fus_deficiencias',
        'fus_transtornos_globais',
        'fus_transtornos_aprendizagem',
        'fus_deficiencia_outro',
        'fus_fl_altas_habilidades',
        'fus_cid',
        'fus_observacao',
    ];

    protected $casts = [
        'fus_fl_pcd' => 'boolean',
        'fus_fl_altas_habilidades' => 'boolean',
        'fus_deficiencias' => 'array',
        'fus_transtornos_globais' => 'array',
        'fus_transtornos_aprendizagem' => 'array',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fus_fun_id', 'fun_id');
    }
}
