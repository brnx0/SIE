<?php

namespace App\Models\Funcionario;

use App\Models\Escola\Escola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioLotacao extends Model
{
    protected $table = 'edu_funcionario_lotacao';
    protected $primaryKey = 'lot_id';

    public function getRouteKeyName(): string
    {
        return 'lot_id';
    }

    const CREATED_AT = 'lot_created_at';
    const UPDATED_AT = 'lot_updated_at';

    protected $fillable = [
        'lot_adm_id',
        'lot_esc_id',
        'lot_crg_id',
        'lot_vinculo',
        'lot_situacao_funcional',
        'lot_criterio_acesso',
        'lot_dt_inicio',
        'lot_dt_fim',
        'lot_fl_ativo',
        'lot_funcoes_sala_aula',
    ];

    protected $casts = [
        'lot_dt_inicio' => 'date',
        'lot_dt_fim' => 'date',
        'lot_fl_ativo' => 'boolean',
        'lot_funcoes_sala_aula' => 'array',
    ];

    public function admissao(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAdmissao::class, 'lot_adm_id', 'adm_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'lot_esc_id', 'esc_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'lot_crg_id', 'crg_id');
    }
}
