<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Visita técnica a uma unidade escolar.
 *
 * Programação → execução (questionário respondido) → ateste. O ateste é ato de
 * outra pessoa: quem realiza a visita não a atesta.
 */
class Visita extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    public const SITUACAO_PROGRAMADA = 'PROGRAMADA';
    public const SITUACAO_REALIZADA  = 'REALIZADA';
    public const SITUACAO_CANCELADA  = 'CANCELADA';
    public const SITUACAO_ATESTADA   = 'ATESTADA';

    public const SITUACOES = [
        self::SITUACAO_PROGRAMADA,
        self::SITUACAO_REALIZADA,
        self::SITUACAO_CANCELADA,
        self::SITUACAO_ATESTADA,
    ];

    protected $table = 'ali_visita';
    protected $primaryKey = 'vis_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'vis_created_at';
    const UPDATED_AT = 'vis_updated_at';
    const DELETED_AT = 'vis_deleted_at';

    protected $fillable = [
        'vis_esc_id',
        'vis_tpv_id',
        'vis_tpq_id',
        'vis_fun_id',
        'vis_usr_id',
        'vis_dt_programada',
        'vis_dt_realizada',
        'vis_situacao',
        'vis_fun_ateste_id',
        'vis_dt_ateste',
        'vis_parecer',
        'vis_obs',
        'vis_motivo_cancelamento',
    ];

    protected $casts = [
        'vis_esc_id'        => 'integer',
        'vis_tpv_id'        => 'integer',
        'vis_tpq_id'        => 'integer',
        'vis_fun_id'        => 'integer',
        'vis_usr_id'        => 'integer',
        'vis_fun_ateste_id' => 'integer',
        'vis_dt_programada' => 'date',
        'vis_dt_realizada'  => 'date',
        'vis_dt_ateste'     => 'date',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Escola::class, 'vis_esc_id', 'esc_id');
    }

    public function tipoVisita(): BelongsTo
    {
        return $this->belongsTo(TipoVisita::class, 'vis_tpv_id', 'tpv_id');
    }

    public function tipoQuestionario(): BelongsTo
    {
        return $this->belongsTo(TipoQuestionario::class, 'vis_tpq_id', 'tpq_id');
    }

    /** Técnico que realiza a visita. */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Funcionario\Funcionario::class, 'vis_fun_id', 'fun_id');
    }

    /** Quem atestou — obrigatoriamente diferente do técnico. */
    public function atestador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Funcionario\Funcionario::class, 'vis_fun_ateste_id', 'fun_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(VisitaResposta::class, 'vre_vis_id', 'vis_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(Anexo::class, 'anexavel', 'anx_anexavel_type', 'anx_anexavel_id', 'vis_id');
    }

    /** Visita atestada é registro fechado: nem dados nem respostas mudam mais. */
    public function editavel(): bool
    {
        return in_array($this->vis_situacao, [self::SITUACAO_PROGRAMADA, self::SITUACAO_REALIZADA], true);
    }

    public function atestada(): bool
    {
        return $this->vis_situacao === self::SITUACAO_ATESTADA;
    }

    /** Só visita realizada pode ser atestada — não se atesta o que não aconteceu. */
    public function podeAtestar(): bool
    {
        return $this->vis_situacao === self::SITUACAO_REALIZADA;
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereHas('escola', fn ($e) => $e->whereRaw('esc_nome ilike ?', ["%{$q}%"]));
    }
}
