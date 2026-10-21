<?php

namespace App\Models\Parametro;

use App\Models\Diario\DiarioDiagnostico;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Nomenclatura de cada resultado da Avaliação Diagnóstica (rede inteira).
 * As opções em si são fixas (DiarioDiagnostico::OPCOES) — só o texto e a sigla
 * apresentados são parametrizáveis.
 */
class TermoDiagnostico extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'cfg_termo_diagnostico';
    protected $primaryKey = 'tdg_id';

    const CREATED_AT = 'tdg_created_at';
    const UPDATED_AT = 'tdg_updated_at';

    private const CACHE_KEY = 'termos_diagnostico';

    /** Siglas padrão (usadas se a opção não estiver cadastrada). */
    public const SIGLAS_PADRAO = [
        'autonomia'    => 'A',
        'apoio'        => 'Ap',
        'nao_realiza'  => 'NR',
        'nao_trabalha' => 'NT',
    ];

    protected $fillable = [
        'tdg_opcao',
        'tdg_descricao',
        'tdg_sigla',
        'tdg_ordem',
        'tdg_updated_by_id',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tdg_updated_by_id');
    }

    /** opção → rótulo, na ordem das opções. */
    public static function rotulos(): array
    {
        return array_map(fn ($t) => $t['descricao'], self::termos());
    }

    /** opção → sigla, na ordem das opções. */
    public static function siglas(): array
    {
        return array_map(fn ($t) => $t['sigla'], self::termos());
    }

    /**
     * opção → [descricao, sigla]. Sempre cobre todas as opções válidas: o que não
     * estiver cadastrado cai no padrão do código.
     *
     * @return array<string, array{descricao: string, sigla: string}>
     */
    public static function termos(): array
    {
        $cadastrados = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->get(['tdg_opcao', 'tdg_descricao', 'tdg_sigla'])
            ->keyBy('tdg_opcao')
            ->map(fn ($t) => ['descricao' => $t->tdg_descricao, 'sigla' => $t->tdg_sigla])
            ->all());

        $termos = [];
        foreach (DiarioDiagnostico::OPCOES as $opcao => $rotuloPadrao) {
            $termos[$opcao] = $cadastrados[$opcao] ?? [
                'descricao' => $rotuloPadrao,
                'sigla'     => self::SIGLAS_PADRAO[$opcao] ?? $opcao,
            ];
        }

        return $termos;
    }
}
