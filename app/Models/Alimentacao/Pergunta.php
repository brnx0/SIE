<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pergunta do questionário de visita técnica.
 *
 * Editar ou inativar não afeta visita já respondida: a resposta guarda o texto
 * e o tipo congelados no dia (ver `VisitaResposta`).
 */
class Pergunta extends Model
{
    use \App\Models\Concerns\Auditable;

    public const TIPO_SIM_NAO  = 'SIM_NAO';
    public const TIPO_NOTA     = 'NOTA';
    public const TIPO_TEXTO    = 'TEXTO';
    public const TIPO_MULTIPLA = 'MULTIPLA';

    public const TIPOS = [self::TIPO_SIM_NAO, self::TIPO_NOTA, self::TIPO_TEXTO, self::TIPO_MULTIPLA];

    /** Rótulos para a tela; a chave é o que vai para o banco. */
    public const TIPOS_LABEL = [
        self::TIPO_SIM_NAO  => 'Sim / Não',
        self::TIPO_NOTA     => 'Nota (0 a 10)',
        self::TIPO_TEXTO    => 'Texto livre',
        self::TIPO_MULTIPLA => 'Múltipla escolha',
    ];

    protected $table = 'ali_pergunta';
    protected $primaryKey = 'prg_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'prg_created_at';
    const UPDATED_AT = 'prg_updated_at';

    protected $fillable = [
        'prg_tpq_id',
        'prg_ordem',
        'prg_texto',
        'prg_tipo_resposta',
        'prg_fl_obrigatoria',
        'prg_fl_ativo',
    ];

    protected $casts = [
        'prg_tpq_id'         => 'integer',
        'prg_ordem'          => 'integer',
        'prg_fl_obrigatoria' => 'boolean',
        'prg_fl_ativo'       => 'boolean',
    ];

    public function tipoQuestionario(): BelongsTo
    {
        return $this->belongsTo(TipoQuestionario::class, 'prg_tpq_id', 'tpq_id');
    }

    public function opcoes(): HasMany
    {
        return $this->hasMany(PerguntaOpcao::class, 'pro_prg_id', 'prg_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(VisitaResposta::class, 'vre_prg_id', 'prg_id');
    }

    public function ehMultipla(): bool
    {
        return $this->prg_tipo_resposta === self::TIPO_MULTIPLA;
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('prg_fl_ativo', true);
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->whereRaw('prg_texto ilike ?', ["%{$q}%"]);
    }
}
