<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\ConversaoUnidade;
use Illuminate\Validation\ValidationException;

/**
 * Regras de integridade das conversões, compartilhadas entre a conversão global
 * (tela da unidade de medida) e a conversão específica de item (tela do item).
 */
class ValidadorConversao
{
    /**
     * Bloqueia cadastrar B→A quando A→B já existe no mesmo escopo.
     *
     * O ConversorUnidade resolve a inversa sozinho (1/fator), então a regra oposta
     * é redundante — e, pior, pode contradizer a original (KG→G = 1000 convivendo
     * com G→KG = 0,5). Com as duas cadastradas, o resultado da conversão passaria
     * a depender da ordem de busca das regras.
     *
     * @param int|null $itmId escopo: null = regra global, id = regra do item
     * @param string   $campo campo do formulário que recebe a mensagem de erro
     */
    public function assertSemRegraInversa(?int $itmId, int $origemId, int $destinoId, string $campo): void
    {
        $inversa = ConversaoUnidade::query()
            ->when($itmId === null,
                fn ($q) => $q->whereNull('cvu_itm_id'),
                fn ($q) => $q->where('cvu_itm_id', $itmId))
            ->where('cvu_umd_origem_id', $destinoId)
            ->where('cvu_umd_destino_id', $origemId)
            ->with(['unidadeOrigem:umd_id,umd_sigla', 'unidadeDestino:umd_id,umd_sigla'])
            ->first();

        if (! $inversa) {
            return;
        }

        $de   = $inversa->unidadeOrigem?->umd_sigla;
        $para = $inversa->unidadeDestino?->umd_sigla;

        throw ValidationException::withMessages([
            $campo => "Já existe a conversão {$de} → {$para} (fator {$this->formatarFator($inversa->cvu_fator)})."
                .' O sistema já faz a conversão no sentido contrário automaticamente — não cadastre a regra inversa.',
        ]);
    }

    /** 1000.000000 → "1000"; 0.500000 → "0,5" */
    private function formatarFator(string|float $fator): string
    {
        return rtrim(rtrim(number_format((float) $fator, 6, ',', '.'), '0'), ',');
    }
}
