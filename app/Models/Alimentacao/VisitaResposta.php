<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resposta de uma pergunta numa visita.
 *
 * Guarda o texto e o tipo da pergunta congelados no momento da resposta. Sem
 * isso, corrigir a redação de uma pergunta reescreveria o que foi perguntado em
 * visitas antigas — e o relatório passaria a mostrar uma pergunta que ninguém
 * respondeu daquele jeito.
 */
class VisitaResposta extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_visita_resposta';
    protected $primaryKey = 'vre_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'vre_created_at';
    const UPDATED_AT = 'vre_updated_at';

    protected $fillable = [
        'vre_vis_id',
        'vre_prg_id',
        'vre_pro_id',
        'vre_pergunta_texto',
        'vre_tipo_resposta',
        'vre_resposta',
        'vre_obs',
    ];

    protected $casts = [
        'vre_vis_id' => 'integer',
        'vre_prg_id' => 'integer',
        'vre_pro_id' => 'integer',
    ];

    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class, 'vre_vis_id', 'vis_id');
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(Pergunta::class, 'vre_prg_id', 'prg_id');
    }

    public function opcao(): BelongsTo
    {
        return $this->belongsTo(PerguntaOpcao::class, 'vre_pro_id', 'pro_id');
    }

    public function respondida(): bool
    {
        return $this->vre_resposta !== null && $this->vre_resposta !== '';
    }

    /** Resposta formatada para leitura (relatório, tela de consulta). */
    public function textoResposta(): string
    {
        if (! $this->respondida()) {
            return '—';
        }

        return match ($this->vre_tipo_resposta) {
            Pergunta::TIPO_SIM_NAO  => $this->vre_resposta === 'SIM' ? 'Sim' : 'Não',
            Pergunta::TIPO_MULTIPLA => $this->opcao?->pro_texto ?? $this->vre_resposta,
            default                 => (string) $this->vre_resposta,
        };
    }
}
