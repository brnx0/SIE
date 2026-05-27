<?php

namespace App\Models\Funcionario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuncionarioAdmissao extends Model
{
    use SoftDeletes;

    protected $table = 'edu_funcionario_admissao';
    protected $primaryKey = 'adm_id';

    public function getRouteKeyName(): string
    {
        return 'adm_id';
    }

    const CREATED_AT = 'adm_created_at';
    const UPDATED_AT = 'adm_updated_at';
    const DELETED_AT = 'adm_deleted_at';

    protected $fillable = [
        'adm_fun_id',
        'adm_matricula',
        'adm_dt_admissao',
        'adm_crg_id',
        'adm_escolaridade_admissao',
    ];

    protected $casts = [
        'adm_dt_admissao' => 'date',
        'adm_escolaridade_admissao' => 'integer',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'adm_fun_id', 'fun_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'adm_crg_id', 'crg_id');
    }

    public function lotacoes(): HasMany
    {
        return $this->hasMany(FuncionarioLotacao::class, 'lot_adm_id', 'adm_id');
    }
}
