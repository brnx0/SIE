<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreAditivoRequest;
use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\ContratoAditivo;
use App\Services\Alimentacao\AplicadorAditivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aditivos do contrato — sub-lista da tela de edição.
 *
 * Não há update nem destroy: o aditivo é o log da alteração do contrato, e
 * reescrever ou apagar faria o histórico contar uma versão diferente do que
 * aconteceu. Para desfazer, existe o cancelamento — que reverte o contrato e
 * fica registrado com autor, data e justificativa.
 */
class ContratoAditivoController extends Controller
{
    public function store(StoreAditivoRequest $request, int $contratoId): RedirectResponse
    {
        $contrato = Contrato::findOrFail($contratoId);

        $aditivo = app(AplicadorAditivo::class)
            ->aplicar($contrato, $request->validated(), $request->user()?->id);

        return back()->with('success', "Aditivo {$aditivo->adt_nr_aditivo} aplicado ao contrato.");
    }

    public function cancelar(Request $request, int $contratoId, int $aditivoId): RedirectResponse
    {
        $dados = $request->validate(
            ['adt_justificativa_cancelamento' => ['required', 'string', 'min:5']],
            [
                'adt_justificativa_cancelamento.required' => 'Informe o motivo do cancelamento.',
                'adt_justificativa_cancelamento.min'      => 'Descreva o motivo do cancelamento.',
            ],
            ['adt_justificativa_cancelamento' => 'justificativa do cancelamento'],
        );

        $aditivo = ContratoAditivo::where('adt_ctr_id', $contratoId)->findOrFail($aditivoId);

        app(AplicadorAditivo::class)->cancelar(
            $aditivo,
            $dados['adt_justificativa_cancelamento'],
            $request->user()?->id,
        );

        return back()->with('success', "Aditivo {$aditivo->adt_nr_aditivo} cancelado. O contrato voltou às condições anteriores a ele.");
    }
}
