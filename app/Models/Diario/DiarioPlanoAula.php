<?php

namespace App\Models\Diario;

use App\Models\Disciplina\Disciplina;
use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\Serie\Serie;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioPlanoAula extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public static $snakeAttributes = false;

    protected $table = 'edu_diario_plano_aula';
    protected $primaryKey = 'dpa_id';

    const CREATED_AT = 'dpa_created_at';
    const UPDATED_AT = 'dpa_updated_at';
    const DELETED_AT = 'dpa_deleted_at';

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
        'dpa_user_id',
        'dpa_esc_id',
        'dpa_anl_id',
        'dpa_ser_id',
        // Opcional: só usado em série com turma multi (ser_fl_multi).
        'dpa_tur_id',
        'dpa_dis_id',
        'dpa_uni_id',
        'dpa_tema',
        'dpa_dt_inicio',
        'dpa_dt_fim',
        'dpa_objeto_conhecimento',
        'dpa_estrategias',
        'dpa_recursos',
        'dpa_competencias',
        'dpa_avaliacao',
        'dpa_objetivos_complementares',
        'dpa_obs_coordenador',
        'dpa_status',
        'dpa_validado_por_user_id',
        'dpa_validado_em',
    ];

    protected $casts = [
        'dpa_dt_inicio'   => 'date',
        'dpa_dt_fim'      => 'date',
        'dpa_validado_em' => 'datetime',
    ];

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'dpa_validado_por_user_id', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dpa_user_id', 'id');
    }

    /**
     * Professor (funcionário) do autor, derivado via users.fun_id.
     * Mantém o display de nome do professor após a troca fun_id -> user_id.
     */
    public function funcionario(): HasOneThrough
    {
        return $this->hasOneThrough(
            Funcionario::class,
            User::class,
            'id',          // users.id  <- dpa_user_id
            'fun_id',      // edu_funcionario.fun_id <- users.fun_id
            'dpa_user_id', // local em edu_diario_plano_aula
            'fun_id',      // local em users
        );
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'dpa_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'dpa_anl_id', 'anl_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'dpa_ser_id', 'ser_id');
    }

    /** Opcional — preenchida só em série com turma multi (ser_fl_multi). */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Turma\Turma::class, 'dpa_tur_id', 'tur_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'dpa_dis_id', 'dis_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'dpa_uni_id', 'uni_id');
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(DiarioPlanoIndicador::class, 'dpi_dpa_id', 'dpa_id');
    }

    public function isPendente(): bool
    {
        return $this->dpa_status === self::STATUS_PENDENTE;
    }
}
