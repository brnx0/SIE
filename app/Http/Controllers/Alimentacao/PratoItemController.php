<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\Prato;
use App\Models\Alimentacao\PratoItem;
use App\Services\Alimentacao\ConversorUnidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ficha técnica do prato: item + quantidade por aluno. É o que permite calcular
 * a demanda de merenda (alunos × per capita × dias) e dar baixa no estoque a
 * cada preparação.
 */
class PratoItemController extends Controller
{
    public function store(Request $request, int $pratoId): RedirectResponse
    {
        $prato = Prato::findOrFail($pratoId);
        $dados = $this->validar($request, $prato);

        PratoItem::create([
            'pri_prt_id'         => $prato->prt_id,
            'pri_itm_id'         => $dados['pri_itm_id'],
            'pri_umd_id'         => $dados['pri_umd_id'],
            'pri_qtd_per_capita' => $dados['pri_qtd_per_capita'],
        ]);

        return back()->with('success', 'Item adicionado à ficha técnica.');
    }

    public function update(Request $request, int $pratoId, int $pratoItemId): RedirectResponse
    {
        $prato     = Prato::findOrFail($pratoId);
        $pratoItem = PratoItem::where('pri_prt_id', $prato->prt_id)->findOrFail($pratoItemId);
        $dados     = $this->validar($request, $prato, $pratoItemId);

        $pratoItem->update([
            'pri_itm_id'         => $dados['pri_itm_id'],
            'pri_umd_id'         => $dados['pri_umd_id'],
            'pri_qtd_per_capita' => $dados['pri_qtd_per_capita'],
        ]);

        return back()->with('success', 'Item da ficha técnica atualizado.');
    }

    public function destroy(int $pratoId, int $pratoItemId): RedirectResponse
    {
        $pratoItem = PratoItem::where('pri_prt_id', $pratoId)->findOrFail($pratoItemId);

        return $this->safeDelete($pratoItem)
            ?? back()->with('success', 'Item removido da ficha técnica.');
    }

    private function validar(Request $request, Prato $prato, ?int $ignorarId = null): array
    {
        $dados = $request->validate(
            [
                'pri_itm_id' => [
                    'required', 'integer', 'exists:ali_item,itm_id',
                    Rule::unique('ali_prato_item', 'pri_itm_id')
                        ->where(fn ($q) => $q->where('pri_prt_id', $prato->prt_id))
                        ->ignore($ignorarId, 'pri_id'),
                ],
                'pri_umd_id'         => ['required', 'integer', 'exists:ali_unidade_medida,umd_id'],
                'pri_qtd_per_capita' => ['required', 'numeric', 'gt:0'],
            ],
            [
                'pri_itm_id.unique'     => 'Este item já está na ficha técnica deste prato. Edite a quantidade em vez de adicionar de novo.',
                'pri_qtd_per_capita.gt' => 'A quantidade por aluno deve ser maior que zero.',
            ],
            [
                'pri_itm_id'         => 'item',
                'pri_umd_id'         => 'unidade',
                'pri_qtd_per_capita' => 'quantidade por aluno',
            ],
        );

        $this->assertUnidadeConversivel((int) $dados['pri_itm_id'], (int) $dados['pri_umd_id']);

        return $dados;
    }

    /**
     * A unidade do per capita precisa converter para a unidade base do item.
     *
     * Sem isso, o prato aceitaria "200 G de arroz" com o arroz controlado em KG e
     * sem regra G→KG cadastrada: na hora de calcular a demanda ou baixar o estoque,
     * a conversão falharia — longe daqui, e para o usuário errado.
     */
    private function assertUnidadeConversivel(int $itmId, int $umdId): void
    {
        $item = Item::with('unidadeMedida:umd_id,umd_sigla')->findOrFail($itmId);

        if (app(ConversorUnidade::class)->podeConverter($umdId, $item->itm_umd_id, $item->itm_id)) {
            return;
        }

        $unidade = \App\Models\Alimentacao\UnidadeMedida::find($umdId);

        throw ValidationException::withMessages([
            'pri_umd_id' => "Não há regra de conversão de {$unidade?->umd_sigla} para {$item->unidadeMedida?->umd_sigla},"
                ." que é a unidade de controle de {$item->itm_nome}."
                .' Cadastre a conversão na tela do item (ou na unidade de medida, se valer para todos os itens),'
                .' senão a demanda e a baixa de estoque deste prato não terão como ser calculadas.',
        ]);
    }
}
