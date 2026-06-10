<?php

namespace App\Models\Aluno;

use App\Models\Matricula\Matricula;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlunoMovimentacao extends Model
{
    use SoftDeletes;

    protected $table = 'edu_aluno_movimentacao';
    protected $primaryKey = 'mva_id';

    const CREATED_AT = 'mva_created_at';
    const UPDATED_AT = 'mva_updated_at';
    const DELETED_AT = 'mva_deleted_at';

    protected $fillable = [
        'mva_aln_id',
        'mva_tmv_cod',
        'mva_tma_id_origem',
        'mva_tma_id_destino',
        'mva_dt_movimentacao',
        'mva_protocolo',
        'mva_observacao',
        'mva_tmas_extras',
        'mva_user_id',
        'mva_fl_cancelada',
        'mva_cancelada_motivo',
        'mva_cancelada_at',
        'mva_cancelada_user_id',
    ];

    protected $casts = [
        'mva_dt_movimentacao' => 'date',
        'mva_tmas_extras'     => 'array',
        'mva_fl_cancelada'    => 'boolean',
        'mva_cancelada_at'    => 'datetime',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'mva_aln_id', 'aln_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoMovimentacao::class, 'mva_tmv_cod', 'tmv_cod');
    }

    public function matriculaOrigem(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'mva_tma_id_origem', 'tma_id');
    }

    public function matriculaDestino(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'mva_tma_id_destino', 'tma_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mva_user_id');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mva_cancelada_user_id');
    }
}
