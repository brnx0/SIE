<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\Movimento;
use App\Models\Alimentacao\Preparacao;
use App\Models\Alimentacao\PreparacaoItem;
use App\Models\Alimentacao\Prato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirmação da preparação diária: transforma o que a cozinha usou em baixa de
 * estoque (CONSUMO) na escola.
 *
 * Enquanto está em rascunho, a preparação é só um registro do que foi feito;
 * confirmar é o ato que mexe no saldo — e não dá para desfazer por aqui, só
 * estornando os movimentos.
 */
class ConfirmaPreparacao
{
    public function __construct(private MovimentaEstoque $estoque)
    {
    }

    /**
     * Monta os itens da preparação a partir da ficha técnica do prato.
     * Previsto = per capita × porções servidas; utilizado começa igual ao
     * previsto, porque o caminho comum é a cozinha seguir a ficha.
     */
    public function montarItens(Preparacao $preparacao): void
    {
        $prato = Prato::with('itens')->findOrFail($preparacao->prd_prt_id);

        if ($prato->itens->isEmpty()) {
            throw ValidationException::withMessages([
                'prd_prt_id' => "O prato \"{$prato->prt_nome}\" não tem ficha técnica."
                    .' Sem os itens e o per capita não há como calcular o consumo nem baixar o estoque.',
            ]);
        }

        DB::transaction(function () use ($preparacao, $prato) {
            $preparacao->itens()->delete();

            foreach ($prato->itens as $ficha) {
                $previsto = (float) $ficha->pri_qtd_per_capita * $preparacao->prd_qtd_servida;

                PreparacaoItem::create([
                    'pdi_prd_id'        => $preparacao->prd_id,
                    'pdi_itm_id'        => $ficha->pri_itm_id,
                    'pdi_umd_id'        => $ficha->pri_umd_id,
                    'pdi_qtd_prevista'  => $previsto,
                    'pdi_qtd_utilizada' => $previsto,
                ]);
            }
        });
    }

    /** Confirma e baixa o estoque da escola, por FEFO. */
    public function confirmar(Preparacao $preparacao, ?int $usuarioId): Preparacao
    {
        return DB::transaction(function () use ($preparacao, $usuarioId) {
            $preparacao = Preparacao::with('itens')->lockForUpdate()->findOrFail($preparacao->prd_id);

            // Confirmar duas vezes baixaria o estoque em dobro.
            if (! $preparacao->emRascunho()) {
                throw ValidationException::withMessages([
                    'situacao' => "Esta preparação já está {$preparacao->prd_situacao} e não pode ser confirmada de novo.",
                ]);
            }

            $usados = $preparacao->itens->filter(fn ($i) => (float) $i->pdi_qtd_utilizada > 0);

            if ($usados->isEmpty()) {
                throw ValidationException::withMessages([
                    'situacao' => 'Nenhum item foi utilizado. Informe as quantidades usadas antes de confirmar.',
                ]);
            }

            foreach ($usados as $linha) {
                $item = Item::findOrFail($linha->pdi_itm_id);

                $this->estoque->baixarFefo(
                    $preparacao->prd_esc_id,
                    $item,
                    (float) $linha->pdi_qtd_utilizada,
                    $linha->pdi_umd_id,
                    Movimento::TIPO_CONSUMO,
                    $preparacao->prd_dt->toDateString(),
                    [
                        'mov_fun_id' => $preparacao->prd_fun_id,
                        'mov_usr_id' => $usuarioId,
                        'mov_origem' => 'preparacao:'.$preparacao->prd_id,
                        'mov_obs'    => "{$preparacao->prd_refeicao} · {$preparacao->prd_turno} · {$preparacao->prd_qtd_servida} porções",
                    ],
                );
            }

            $preparacao->update([
                'prd_situacao' => Preparacao::SITUACAO_CONFIRMADA,
                'prd_usr_id'   => $preparacao->prd_usr_id ?? $usuarioId,
            ]);

            return $preparacao;
        });
    }
}
