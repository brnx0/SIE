<?php

namespace App\Models\Aluno;

use App\Models\Disciplina\Disciplina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Disciplina (com média final anual) de um ano escolar lançado manualmente.
 */
class HistoricoManualDisciplina extends Model
{
    use SoftDeletes;

    protected $table = 'edu_historico_manual_disciplina';
    protected $primaryKey = 'hmd_id';

    const CREATED_AT = 'hmd_created_at';
    const UPDATED_AT = 'hmd_updated_at';
    const DELETED_AT = 'hmd_deleted_at';

    protected $fillable = [
        'hmd_hmn_id',
        'hmd_dis_id',
        'hmd_mf',
        'hmd_cnc_id',
        'hmd_conceito',
        'hmd_ordem',
    ];

    protected $casts = [
        'hmd_mf'    => 'decimal:1',
        'hmd_cnc_id' => 'integer',
        'hmd_ordem' => 'integer',
    ];

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'hmd_dis_id', 'dis_id');
    }

    public function conceito(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Parametro\Conceito::class, 'hmd_cnc_id', 'cnc_id');
    }

    /**
     * Sigla do conceito a exibir: o texto livre digitado tem precedência sobre
     * o catálogo (cfg_conceito), usado só pelos lançamentos antigos.
     */
    public function siglaConceito(): ?string
    {
        return $this->hmd_conceito ?: $this->conceito?->cnc_sigla;
    }
}
