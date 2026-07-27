<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\Movimento;
use App\Models\Alimentacao\OrdemFornecimento;
use App\Models\Alimentacao\Recebimento;
use App\Models\Alimentacao\RecebimentoItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aceite do recebimento: transforma a conferência em estoque.
 *
 * É o único caminho de entrada do fornecedor no estoque central. Faz três coisas
 * numa transação só — dar entrada, atualizar o entregue na ordem e fechar a
 * situação do recebimento e da ordem. Se qualquer parte falhar, nada vale: o
 * estoque não pode existir sem o registro da conferência que o originou.
 */
class AceitaRecebimento
{
    public function __construct(private MovimentaEstoque $estoque)
    {
    }

    public function aceitar(Recebimento $recebimento, ?int $usuarioId): Recebimento
    {
        return DB::transaction(function () use ($recebimento, $usuarioId) {
            $recebimento = Recebimento::with(['itens.item', 'itens.ordemItem', 'ordem'])
                ->lockForUpdate()
                ->findOrFail($recebimento->rcb_id);

            // Aceitar duas vezes daria entrada dobrada no estoque.
            if (! $recebimento->emConferencia()) {
                throw ValidationException::withMessages([
                    'situacao' => "Este recebimento já está {$recebimento->rcb_situacao} e não pode ser aceito de novo.",
                ]);
            }

            $conferidos = $recebimento->itens->filter(fn ($i) => $i->conferido());

            if ($conferidos->isEmpty()) {
                throw ValidationException::withMessages([
                    'situacao' => 'Nenhum item foi conferido. Informe o que chegou (ou o que foi rejeitado) antes de aceitar.',
                ]);
            }

            foreach ($conferidos as $linha) {
                $this->darEntrada($recebimento, $linha, $usuarioId);
                $this->atualizarEntregueNaOrdem($linha);
            }

            $recebimento->update([
                'rcb_situacao' => $this->situacaoDoRecebimento($recebimento),
                'rcb_usr_id'   => $recebimento->rcb_usr_id ?? $usuarioId,
            ]);

            $this->atualizarSituacaoDaOrdem($recebimento->ordem);

            return $recebimento;
        });
    }

    /** Só o que foi aceito entra no estoque; o rejeitado não existe fisicamente. */
    private function darEntrada(Recebimento $recebimento, RecebimentoItem $linha, ?int $usuarioId): void
    {
        $qtd = (float) $linha->rci_qtd_recebida;

        if ($qtd <= 0) {
            return;
        }

        if (! $linha->rci_lot_id) {
            throw ValidationException::withMessages([
                'itens' => "Informe o lote de {$linha->item?->itm_nome}: sem lote não há como controlar validade nem baixar por FEFO.",
            ]);
        }

        $ordem = $recebimento->ordem;

        $this->estoque->entrada(
            null, // fornecedor entrega sempre no central
            $linha->lote,
            $qtd,
            $linha->rci_umd_id,
            $recebimento->rcb_dt->toDateString(),
            [
                'mov_frn_id' => $ordem?->contrato?->ctr_frn_id,
                'mov_ofn_id' => $ordem?->ofn_id,
                'mov_fun_id' => $recebimento->rcb_fun_id,
                'mov_usr_id' => $usuarioId,
                'mov_origem' => 'recebimento:'.$recebimento->rcb_id,
                'mov_obs'    => $recebimento->rcb_nr_nota ? "Nota {$recebimento->rcb_nr_nota}" : null,
            ],
            // Custo vem do preço da ordem: é o que valoriza o estoque.
            $linha->ordemItem ? (float) $linha->ordemItem->ofi_vl_unitario : null,
        );
    }

    private function atualizarEntregueNaOrdem(RecebimentoItem $linha): void
    {
        $ordemItem = $linha->ordemItem;

        if (! $ordemItem) {
            return;
        }

        $ordemItem->update([
            'ofi_qtd_entregue' => (float) $ordemItem->ofi_qtd_entregue + (float) $linha->rci_qtd_recebida,
        ]);
    }

    /** Rejeitou tudo = REJEITADO; recebeu menos que o previsto = PARCIAL. */
    private function situacaoDoRecebimento(Recebimento $recebimento): string
    {
        $recebido  = $recebimento->itens->sum(fn ($i) => (float) $i->rci_qtd_recebida);
        $previsto  = $recebimento->itens->sum(fn ($i) => (float) $i->rci_qtd_prevista);

        if ($recebido <= 0) {
            return Recebimento::SITUACAO_REJEITADO;
        }

        return $recebido < $previsto
            ? Recebimento::SITUACAO_PARCIAL
            : Recebimento::SITUACAO_ACEITO;
    }

    /**
     * A ordem fecha quando todos os itens foram entregues por inteiro; enquanto
     * faltar algo, fica PARCIAL — é o que mostra o que o fornecedor ainda deve.
     */
    private function atualizarSituacaoDaOrdem(?OrdemFornecimento $ordem): void
    {
        if (! $ordem || $ordem->ofn_situacao === OrdemFornecimento::SITUACAO_CANCELADA) {
            return;
        }

        $itens = $ordem->itens()->get();

        if ($itens->isEmpty()) {
            return;
        }

        $tudoEntregue = $itens->every(fn ($i) => (float) $i->ofi_qtd_entregue >= (float) $i->ofi_qtd);
        $algoEntregue = $itens->contains(fn ($i) => (float) $i->ofi_qtd_entregue > 0);

        $ordem->update([
            'ofn_situacao' => match (true) {
                $tudoEntregue => OrdemFornecimento::SITUACAO_ENTREGUE,
                $algoEntregue => OrdemFornecimento::SITUACAO_PARCIAL,
                default       => OrdemFornecimento::SITUACAO_EMITIDA,
            },
        ]);
    }
}
