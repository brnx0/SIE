<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\ContratoItem;
use App\Models\Alimentacao\Item;
use App\Services\Alimentacao\ConversorUnidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Itens contratados: quantidade e preço unitário. Sub-lista da tela do contrato. */
class ContratoItemController extends Controller
{
    public function store(Request $request, int $contratoId): RedirectResponse
    {
        $contrato = Contrato::findOrFail($contratoId);
        $dados    = $this->validar($request, $contrato);

        ContratoItem::create([
            'cti_ctr_id'      => $contrato->ctr_id,
            'cti_itm_id'      => $dados['cti_itm_id'],
            'cti_umd_id'      => $dados['cti_umd_id'],
            'cti_qtd'         => $dados['cti_qtd'],
            'cti_vl_unitario' => $dados['cti_vl_unitario'],
        ]);

        return back()->with('success', 'Item adicionado ao contrato.');
    }

    public function update(Request $request, int $contratoId, int $itemId): RedirectResponse
    {
        $contrato     = Contrato::findOrFail($contratoId);
        $contratoItem = ContratoItem::where('cti_ctr_id', $contrato->ctr_id)->findOrFail($itemId);
        $dados        = $this->validar($request, $contrato, $itemId);

        // A quantidade contratada, depois que há ordens, só muda por aditivo de QTD.
        $this->assertQtdAlteravel($contratoItem, (float) $dados['cti_qtd']);

        $contratoItem->update([
            'cti_itm_id'      => $dados['cti_itm_id'],
            'cti_umd_id'      => $dados['cti_umd_id'],
            'cti_qtd'         => $dados['cti_qtd'],
            'cti_vl_unitario' => $dados['cti_vl_unitario'],
        ]);

        return back()->with('success', 'Item do contrato atualizado.');
    }

    public function destroy(int $contratoId, int $itemId): RedirectResponse
    {
        $contratoItem = ContratoItem::where('cti_ctr_id', $contratoId)->findOrFail($itemId);

        if ($contratoItem->qtdPedida() > 0) {
            return back()->with('error', 'Este item já foi pedido em ordens de fornecimento e não pode ser removido do contrato.');
        }

        return $this->safeDelete($contratoItem)
            ?? back()->with('success', 'Item removido do contrato.');
    }

    private function validar(Request $request, Contrato $contrato, ?int $ignorarId = null): array
    {
        $dados = $request->validate(
            [
                'cti_itm_id' => [
                    'required', 'integer', 'exists:ali_item,itm_id',
                    Rule::unique('ali_contrato_item', 'cti_itm_id')
                        ->where(fn ($q) => $q->where('cti_ctr_id', $contrato->ctr_id))
                        ->ignore($ignorarId, 'cti_id'),
                ],
                'cti_umd_id'      => ['required', 'integer', 'exists:ali_unidade_medida,umd_id'],
                'cti_qtd'         => ['required', 'numeric', 'gt:0'],
                'cti_vl_unitario' => ['required', 'numeric', 'gt:0'],
            ],
            [
                'cti_itm_id.unique'  => 'Este item já está no contrato. Edite a quantidade em vez de adicionar de novo.',
                'cti_qtd.gt'         => 'A quantidade deve ser maior que zero.',
                'cti_vl_unitario.gt' => 'O valor unitário deve ser maior que zero.',
            ],
            [
                'cti_itm_id'      => 'item',
                'cti_umd_id'      => 'unidade',
                'cti_qtd'         => 'quantidade',
                'cti_vl_unitario' => 'valor unitário',
            ],
        );

        $this->assertUnidadeConversivel((int) $dados['cti_itm_id'], (int) $dados['cti_umd_id']);

        return $dados;
    }

    /**
     * A unidade contratada precisa converter para a unidade base do item: é ela
     * que o recebimento vai usar para dar entrada no estoque. Sem regra de
     * conversão, a entrada falharia na hora de receber a mercadoria.
     */
    private function assertUnidadeConversivel(int $itmId, int $umdId): void
    {
        $item = Item::with('unidadeMedida:umd_id,umd_sigla')->findOrFail($itmId);

        if (app(ConversorUnidade::class)->podeConverter($umdId, $item->itm_umd_id, $item->itm_id)) {
            return;
        }

        $unidade = \App\Models\Alimentacao\UnidadeMedida::find($umdId);

        throw ValidationException::withMessages([
            'cti_umd_id' => "Não há regra de conversão de {$unidade?->umd_sigla} para {$item->unidadeMedida?->umd_sigla},"
                ." que é a unidade de controle de {$item->itm_nome}."
                .' Cadastre a conversão na tela do item, senão o recebimento desta compra não terá como dar entrada no estoque.',
        ]);
    }

    /**
     * Reduzir a quantidade abaixo do que já foi pedido em ordens deixaria o
     * contrato estourado retroativamente. Aumentar/reduzir com ordens em curso
     * é alteração contratual: passa por aditivo de QTD, que registra o de/para.
     */
    private function assertQtdAlteravel(ContratoItem $contratoItem, float $novaQtd): void
    {
        $pedida = $contratoItem->qtdPedida();

        if ($pedida <= 0 || (float) $contratoItem->cti_qtd === $novaQtd) {
            return;
        }

        throw ValidationException::withMessages([
            'cti_qtd' => 'Este item já foi pedido em ordens de fornecimento. Altere a quantidade lançando um'
                .' aditivo do tipo QTD, que é o que registra o histórico da mudança.',
        ]);
    }
}
