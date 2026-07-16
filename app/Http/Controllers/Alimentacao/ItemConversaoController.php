<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\Item;
use App\Services\Alimentacao\ValidadorConversao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Conversões ESPECÍFICAS de um item (ex.: 1 CX de arroz = 30 KG), editadas como
 * sub-lista na tela do item. Regra que vale para qualquer item (1 KG = 1000 G)
 * é cadastrada na tela da unidade de medida.
 *
 * O destino é sempre a unidade base do item (`itm_umd_id`): é para ela que todo
 * saldo e movimento são convertidos, então uma regra que aponte para outro lugar
 * não seria usada por ninguém.
 */
class ItemConversaoController extends Controller
{
    public function store(Request $request, int $itemId): RedirectResponse
    {
        $item  = Item::findOrFail($itemId);
        $dados = $this->validar($request, $item);

        ConversaoUnidade::create([
            'cvu_itm_id'         => $item->itm_id,
            'cvu_umd_origem_id'  => $dados['cvu_umd_origem_id'],
            'cvu_umd_destino_id' => $item->itm_umd_id,
            'cvu_fator'          => $dados['cvu_fator'],
            'cvu_fl_ativo'       => $dados['cvu_fl_ativo'],
        ]);

        return back()->with('success', 'Conversão cadastrada com sucesso.');
    }

    public function update(Request $request, int $itemId, int $conversaoId): RedirectResponse
    {
        $item      = Item::findOrFail($itemId);
        $conversao = ConversaoUnidade::where('cvu_itm_id', $item->itm_id)->findOrFail($conversaoId);
        $dados     = $this->validar($request, $item, $conversaoId);

        $conversao->update([
            'cvu_umd_origem_id'  => $dados['cvu_umd_origem_id'],
            'cvu_umd_destino_id' => $item->itm_umd_id,
            'cvu_fator'          => $dados['cvu_fator'],
            'cvu_fl_ativo'       => $dados['cvu_fl_ativo'],
        ]);

        return back()->with('success', 'Conversão atualizada com sucesso.');
    }

    public function destroy(int $itemId, int $conversaoId): RedirectResponse
    {
        $conversao = ConversaoUnidade::where('cvu_itm_id', $itemId)->findOrFail($conversaoId);

        return $this->safeDelete($conversao)
            ?? back()->with('success', 'Conversão removida com sucesso.');
    }

    private function validar(Request $request, Item $item, ?int $ignorarId = null): array
    {
        $request->merge(['cvu_fl_ativo' => $request->boolean('cvu_fl_ativo', true)]);

        $dados = $request->validate(
            [
                'cvu_umd_origem_id' => [
                    'required', 'integer', 'exists:ali_unidade_medida,umd_id',
                    Rule::notIn([$item->itm_umd_id]),
                    Rule::unique('ali_conversao_unidade', 'cvu_umd_origem_id')
                        ->where(fn ($q) => $q->where('cvu_itm_id', $item->itm_id)->where('cvu_umd_destino_id', $item->itm_umd_id))
                        ->ignore($ignorarId, 'cvu_id'),
                ],
                'cvu_fator'    => ['required', 'numeric', 'gt:0'],
                'cvu_fl_ativo' => ['boolean'],
            ],
            [
                'cvu_umd_origem_id.not_in' => 'A unidade de origem deve ser diferente da unidade de controle do item.',
                'cvu_umd_origem_id.unique' => 'Já existe uma conversão cadastrada desta unidade para este item.',
                'cvu_fator.gt'             => 'O fator deve ser maior que zero.',
            ],
            [
                'cvu_umd_origem_id' => 'unidade de origem',
                'cvu_fator'         => 'fator',
                'cvu_fl_ativo'      => 'situação',
            ],
        );

        app(ValidadorConversao::class)->assertSemRegraInversa(
            $item->itm_id,
            (int) $dados['cvu_umd_origem_id'],
            $item->itm_umd_id,
            'cvu_umd_origem_id',
        );

        return $dados;
    }
}
