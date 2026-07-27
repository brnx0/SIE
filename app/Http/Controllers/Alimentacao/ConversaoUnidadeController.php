<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\UnidadeMedida;
use App\Services\Alimentacao\ValidadorConversao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Conversões GLOBAIS (valem para qualquer item), editadas como sub-lista na tela
 * da unidade de medida de origem. Conversão específica de um item (CX de arroz →
 * KG) é cadastrada na tela do Item.
 */
class ConversaoUnidadeController extends Controller
{
    public function store(Request $request, int $umdId): RedirectResponse
    {
        $origem = UnidadeMedida::findOrFail($umdId);
        $dados  = $this->validar($request, $origem->umd_id);

        ConversaoUnidade::create([
            'cvu_itm_id'         => null,
            'cvu_umd_origem_id'  => $origem->umd_id,
            'cvu_umd_destino_id' => $dados['cvu_umd_destino_id'],
            'cvu_fator'          => $dados['cvu_fator'],
            'cvu_fl_ativo'       => $dados['cvu_fl_ativo'],
        ]);

        return back()->with('success', 'Conversão cadastrada com sucesso.');
    }

    public function update(Request $request, int $umdId, int $conversaoId): RedirectResponse
    {
        $conversao = ConversaoUnidade::whereNull('cvu_itm_id')
            ->where('cvu_umd_origem_id', $umdId)
            ->findOrFail($conversaoId);

        $dados = $this->validar($request, $umdId, $conversaoId);

        $conversao->update([
            'cvu_umd_destino_id' => $dados['cvu_umd_destino_id'],
            'cvu_fator'          => $dados['cvu_fator'],
            'cvu_fl_ativo'       => $dados['cvu_fl_ativo'],
        ]);

        return back()->with('success', 'Conversão atualizada com sucesso.');
    }

    public function destroy(int $umdId, int $conversaoId): RedirectResponse
    {
        $conversao = ConversaoUnidade::whereNull('cvu_itm_id')
            ->where('cvu_umd_origem_id', $umdId)
            ->findOrFail($conversaoId);

        return $this->safeDelete($conversao)
            ?? back()->with('success', 'Conversão removida com sucesso.');
    }

    private function validar(Request $request, int $origemId, ?int $ignorarId = null): array
    {
        $request->merge(['cvu_fl_ativo' => $request->boolean('cvu_fl_ativo', true)]);

        $dados = $request->validate(
            [
                'cvu_umd_destino_id' => [
                    'required', 'integer', 'exists:ali_unidade_medida,umd_id',
                    Rule::notIn([$origemId]),
                    Rule::unique('ali_conversao_unidade', 'cvu_umd_destino_id')
                        ->where(fn ($q) => $q->whereNull('cvu_itm_id')->where('cvu_umd_origem_id', $origemId))
                        ->ignore($ignorarId, 'cvu_id'),
                ],
                'cvu_fator'    => ['required', 'numeric', 'gt:0'],
                'cvu_fl_ativo' => ['boolean'],
            ],
            [
                'cvu_umd_destino_id.not_in' => 'A unidade de destino deve ser diferente da unidade de origem.',
                'cvu_umd_destino_id.unique' => 'Já existe uma conversão cadastrada desta unidade para essa unidade de destino.',
                'cvu_fator.gt'              => 'O fator deve ser maior que zero.',
            ],
            [
                'cvu_umd_destino_id' => 'unidade de destino',
                'cvu_fator'          => 'fator',
                'cvu_fl_ativo'       => 'situação',
            ],
        );

        app(ValidadorConversao::class)
            ->assertSemRegraInversa(null, $origemId, (int) $dados['cvu_umd_destino_id'], 'cvu_umd_destino_id');

        return $dados;
    }
}
