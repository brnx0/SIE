<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Cardapio;
use App\Models\Alimentacao\CardapioDia;
use App\Models\Turma\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grade do cardápio: qual prato é servido em cada dia da semana × turno × refeição.
 * Sub-lista da tela de edição do cardápio.
 */
class CardapioDiaController extends Controller
{
    public function store(Request $request, int $cardapioId): RedirectResponse
    {
        $cardapio = Cardapio::findOrFail($cardapioId);
        $dados    = $this->validar($request, $cardapio);

        CardapioDia::create([
            'cdi_crd_id'     => $cardapio->crd_id,
            'cdi_prt_id'     => $dados['cdi_prt_id'],
            'cdi_semana'     => $dados['cdi_semana'] ?? 1,
            'cdi_dia_semana' => $dados['cdi_dia_semana'],
            'cdi_turno'      => $dados['cdi_turno'],
            'cdi_refeicao'   => $dados['cdi_refeicao'],
        ]);

        return back()->with('success', 'Prato incluído no cardápio.');
    }

    public function update(Request $request, int $cardapioId, int $diaId): RedirectResponse
    {
        $cardapio = Cardapio::findOrFail($cardapioId);
        $dia      = CardapioDia::where('cdi_crd_id', $cardapio->crd_id)->findOrFail($diaId);
        $dados    = $this->validar($request, $cardapio, $diaId);

        $dia->update([
            'cdi_prt_id'     => $dados['cdi_prt_id'],
            'cdi_semana'     => $dados['cdi_semana'] ?? 1,
            'cdi_dia_semana' => $dados['cdi_dia_semana'],
            'cdi_turno'      => $dados['cdi_turno'],
            'cdi_refeicao'   => $dados['cdi_refeicao'],
        ]);

        return back()->with('success', 'Cardápio atualizado.');
    }

    public function destroy(int $cardapioId, int $diaId): RedirectResponse
    {
        $dia = CardapioDia::where('cdi_crd_id', $cardapioId)->findOrFail($diaId);

        return $this->safeDelete($dia)
            ?? back()->with('success', 'Prato removido do cardápio.');
    }

    private function validar(Request $request, Cardapio $cardapio, ?int $ignorarId = null): array
    {
        return $request->validate(
            [
                'cdi_prt_id'     => ['required', 'integer', 'exists:ali_prato,prt_id'],
                'cdi_semana'     => ['nullable', 'integer', 'between:1,5'],
                'cdi_dia_semana' => [
                    'required', 'integer', 'between:1,7',
                    // Um slot (semana + dia + turno + refeição) só comporta um prato:
                    // é o mesmo unique que existe no banco, dito na linguagem da tela.
                    Rule::unique('ali_cardapio_dia', 'cdi_dia_semana')
                        ->where(fn ($q) => $q
                            ->where('cdi_crd_id', $cardapio->crd_id)
                            ->where('cdi_semana', $request->input('cdi_semana', 1))
                            ->where('cdi_turno', $request->input('cdi_turno'))
                            ->where('cdi_refeicao', $request->input('cdi_refeicao')))
                        ->ignore($ignorarId, 'cdi_id'),
                ],
                'cdi_turno'    => ['required', 'string', Rule::in(Turma::TURNOS)],
                'cdi_refeicao' => ['required', 'string', Rule::in(CardapioController::REFEICOES)],
            ],
            [
                'cdi_dia_semana.unique' => 'Já existe um prato para este dia, turno e refeição neste cardápio. Remova o atual antes de incluir outro.',
            ],
            [
                'cdi_prt_id'     => 'prato',
                'cdi_semana'     => 'semana',
                'cdi_dia_semana' => 'dia da semana',
                'cdi_turno'      => 'turno',
                'cdi_refeicao'   => 'refeição',
            ],
        );
    }
}
