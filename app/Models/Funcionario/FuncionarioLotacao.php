<?php

namespace App\Models\Funcionario;

use App\Models\Escola\Escola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioLotacao extends Model
{
    protected $table = 'edu_funcionario_lotacao';
    protected $primaryKey = 'lot_id';

    const CREATED_AT = 'lot_created_at';
    const UPDATED_AT = 'lot_updated_at';

    protected $fillable = [
        'lot_adm_id',
        'lot_esc_id',
        'lot_crg_id',
        'lot_ano_inicio',
        'lot_ano_fim',
    ];

    protected $casts = [
        'lot_ano_inicio' => 'integer',
        'lot_ano_fim' => 'integer',
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
