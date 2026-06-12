<?php

namespace App\Models\Diario;

use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\AnoLetivo;
use App\Models\Turma\Turma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioPlanoAee extends Model
{
    use SoftDeletes;

    public static $snakeAttributes = false;

    protected $table = 'edu_diario_plano_aee';
    protected $primaryKey = 'dae_id';

    const CREATED_AT = 'dae_created_at';
    const UPDATED_AT = 'dae_updated_at';
    const DELETED_AT = 'dae_deleted_at';

    const STATUS_PENDENTE  = 'pendente';
    const STATUS_APROVADO  = 'aprovado';
    const STATUS_REPROVADO = 'reprovado';
    const STATUS_CORRECAO  = 'correcao';

    const STATUSES = [
        self::STATUS_PENDENTE,
        self::STATUS_APROVADO,
        self::STATUS_REPROVADO,
        self::STATUS_CORRECAO,
    ];

    protected $fillable = [
        'dae_fun_id',
        'dae_esc_id',
        'dae_anl_id',
        'dae_tur_id',
        'dae_tema',
        'dae_dt_inicio',
        'dae_dt_fim',
        'dae_objetivo',
        'dae_diagnostico',
        'dae_area_desenv',
        'dae_metas',
        'dae_estrategias',
        'dae_recursos',
        'dae_avaliacao',
        'dae_obs_coordenador',
        'dae_status',
    ];

    protected $casts = [
        'dae_dt_inicio' => 'date',
        'dae_dt_fim'    => 'date',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'dae_fun_id', 'fun_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'dae_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'dae_anl_id', 'anl_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'dae_tur_id', 'tur_id');
    }

    public function isPendente(): bool
    {
        return $this->dae_status === self::STATUS_PENDENTE;
    }
}
