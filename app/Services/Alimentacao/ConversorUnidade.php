<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\Item;
use Illuminate\Validation\ValidationException;

/**
 * Converte quantidades entre unidades de medida.
 *
 * Todo saldo e todo movimento de estoque são gravados também na unidade base do
 * item (`itm_umd_id`) — sem isso, um item que entra em CX e sai em KG não fecha
 * saldo. Esta classe é o único ponto que resolve o fator.
 *
 * Ordem de resolução:
 *   1. regra específica do item (`cvu_itm_id` = item)
 *   2. regra global (`cvu_itm_id` nulo)
 * Em ambos os casos a regra inversa (destino → origem) também vale, aplicando 1/fator.
 */
class ConversorUnidade
{
    /** Cache por request: [itmId|'global']["origem:destino"] => fator */
    private array $cache = [];

    /** Converte `$qtd` de `$umdOrigemId` para a unidade base do item. */
    public function paraUnidadeBase(Item $item, float $qtd, int $umdOrigemId): float
    {
        return $this->converter($qtd, $umdOrigemId, $item->itm_umd_id, $item->itm_id);
    }

    /**
     * Converte `$qtd` entre duas unidades. `$itmId` nulo usa só regras globais.
     *
     * @throws ValidationException quando não existe regra cadastrada.
     */
    public function converter(float $qtd, int $umdOrigemId, int $umdDestinoId, ?int $itmId = null): float
    {
        if ($umdOrigemId === $umdDestinoId) {
            return $qtd;
        }

        return $qtd * $this->fator($umdOrigemId, $umdDestinoId, $itmId);
    }

    /** Fator de multiplicação de origem → destino. */
    public function fator(int $umdOrigemId, int $umdDestinoId, ?int $itmId = null): float
    {
        if ($umdOrigemId === $umdDestinoId) {
            return 1.0;
        }

        $chave = "{$umdOrigemId}:{$umdDestinoId}";
        $escopo = $itmId ?? 'global';

        if (isset($this->cache[$escopo][$chave])) {
            return $this->cache[$escopo][$chave];
        }

        $fator = $this->buscarFator($umdOrigemId, $umdDestinoId, $itmId);

        if ($fator === null) {
            throw ValidationException::withMessages([
                'conversao' => 'Não há regra de conversão cadastrada entre as unidades informadas'
                    .($itmId ? ' para este item.' : '.')
                    .' Cadastre a conversão em Alimentação → Unidades de Medida.',
            ]);
        }

        return $this->cache[$escopo][$chave] = $fator;
    }

    /** True se existe regra (não lança). Use para validar formulário antes de gravar. */
    public function podeConverter(int $umdOrigemId, int $umdDestinoId, ?int $itmId = null): bool
    {
        return $umdOrigemId === $umdDestinoId
            || $this->buscarFator($umdOrigemId, $umdDestinoId, $itmId) !== null;
    }

    private function buscarFator(int $umdOrigemId, int $umdDestinoId, ?int $itmId): ?float
    {
        // Item primeiro; sem regra específica, cai na global.
        $escopos = $itmId ? [$itmId, null] : [null];

        foreach ($escopos as $escopo) {
            $direta = $this->regra($escopo, $umdOrigemId, $umdDestinoId);
            if ($direta) {
                return (float) $direta->cvu_fator;
            }

            $inversa = $this->regra($escopo, $umdDestinoId, $umdOrigemId);
            if ($inversa && (float) $inversa->cvu_fator != 0.0) {
                return 1 / (float) $inversa->cvu_fator;
            }
        }

        return null;
    }

    private function regra(?int $itmId, int $origemId, int $destinoId): ?ConversaoUnidade
    {
        return ConversaoUnidade::query()
            ->where('cvu_fl_ativo', true)
            ->when($itmId === null,
                fn ($q) => $q->whereNull('cvu_itm_id'),
                fn ($q) => $q->where('cvu_itm_id', $itmId))
            ->where('cvu_umd_origem_id', $origemId)
            ->where('cvu_umd_destino_id', $destinoId)
            ->first();
    }
}
