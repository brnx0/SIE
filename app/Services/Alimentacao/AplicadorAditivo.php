<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\ContratoAditivo;
use App\Models\Alimentacao\ContratoItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aplica um aditivo ao contrato.
 *
 * O contrato guarda os valores vigentes; o aditivo guarda o de/para. Os dois são
 * gravados na MESMA transação — se o log falhar, a alteração do contrato não vale,
 * senão o histórico mente sobre o que aconteceu.
 */
class AplicadorAditivo
{
    /**
     * @param  array  $dados  já validados pelo StoreAditivoRequest
     */
    public function aplicar(Contrato $contrato, array $dados, ?int $usuarioId): ContratoAditivo
    {
        return DB::transaction(function () use ($contrato, $dados, $usuarioId) {
            // Lock no contrato: dois aditivos simultâneos leriam o mesmo valor
            // "anterior" e o segundo sobrescreveria o primeiro sem aparecer no log.
            $contrato = Contrato::lockForUpdate()->findOrFail($contrato->ctr_id);

            $aditivo = match ($dados['adt_tipo']) {
                ContratoAditivo::TIPO_VALOR  => $this->aditivarValor($contrato, $dados),
                ContratoAditivo::TIPO_PRAZO  => $this->aditivarPrazo($contrato, $dados),
                ContratoAditivo::TIPO_OBJETO => $this->aditivarObjeto($contrato, $dados),
                ContratoAditivo::TIPO_QTD    => $this->aditivarQtd($contrato, $dados),
            };

            $aditivo['adt_ctr_id']        = $contrato->ctr_id;
            $aditivo['adt_nr_aditivo']    = $dados['adt_nr_aditivo'];
            $aditivo['adt_tipo']          = $dados['adt_tipo'];
            $aditivo['adt_dt_assinatura'] = $dados['adt_dt_assinatura'];
            $aditivo['adt_justificativa'] = $dados['adt_justificativa'] ?? null;
            $aditivo['adt_usr_id']        = $usuarioId;

            return ContratoAditivo::create($aditivo);
        });
    }

    /**
     * Cancela um aditivo e devolve o contrato ao valor anterior.
     *
     * Só o ÚLTIMO aditivo não-cancelado de cada tipo pode ser cancelado: se
     * houvesse dois aditivos de VALOR (100k→125k→150k) e o do meio fosse
     * cancelado, para qual valor o contrato voltaria? O anterior dele (100k)
     * apagaria o aditivo seguinte; manter 150k tornaria o cancelamento
     * decorativo. Cancelando do fim para o começo, cada passo é reversível.
     *
     * O aditivo não some — fica no histórico marcado como cancelado, com quem
     * cancelou, quando e por quê.
     */
    public function cancelar(ContratoAditivo $aditivo, string $justificativa, ?int $usuarioId): ContratoAditivo
    {
        return DB::transaction(function () use ($aditivo, $justificativa, $usuarioId) {
            $aditivo  = ContratoAditivo::lockForUpdate()->findOrFail($aditivo->adt_id);
            $contrato = Contrato::lockForUpdate()->findOrFail($aditivo->adt_ctr_id);

            if ($aditivo->adt_fl_cancelado) {
                throw ValidationException::withMessages([
                    'adt_justificativa_cancelamento' => 'Este aditivo já está cancelado.',
                ]);
            }

            $this->assertEhOUltimoDoTipo($aditivo);

            match ($aditivo->adt_tipo) {
                ContratoAditivo::TIPO_VALOR  => $contrato->update(['ctr_vl_total' => $aditivo->adt_vl_anterior]),
                ContratoAditivo::TIPO_PRAZO  => $contrato->update(['ctr_dt_fim' => $aditivo->adt_dt_fim_anterior]),
                ContratoAditivo::TIPO_OBJETO => $contrato->update(['ctr_objeto' => $aditivo->adt_objeto_anterior]),
                ContratoAditivo::TIPO_QTD    => $this->reverterQtd($aditivo),
            };

            $aditivo->update([
                'adt_fl_cancelado'               => true,
                'adt_dt_cancelamento'            => now(),
                'adt_justificativa_cancelamento' => $justificativa,
                'adt_usr_cancelamento_id'        => $usuarioId,
            ]);

            return $aditivo;
        });
    }

    /** Cancelar fora de ordem deixaria indefinido para qual valor o contrato volta. */
    private function assertEhOUltimoDoTipo(ContratoAditivo $aditivo): void
    {
        $posterior = ContratoAditivo::query()
            ->where('adt_ctr_id', $aditivo->adt_ctr_id)
            ->where('adt_tipo', $aditivo->adt_tipo)
            ->where('adt_fl_cancelado', false)
            ->where('adt_id', '!=', $aditivo->adt_id)
            // QTD é por item: dois itens diferentes não competem entre si.
            ->when($aditivo->adt_tipo === ContratoAditivo::TIPO_QTD,
                fn ($q) => $q->where('adt_cti_id', $aditivo->adt_cti_id))
            ->where('adt_id', '>', $aditivo->adt_id)
            ->orderByDesc('adt_id')
            ->first();

        if (! $posterior) {
            return;
        }

        throw ValidationException::withMessages([
            'adt_justificativa_cancelamento' => "Existe um aditivo mais recente do tipo {$aditivo->adt_tipo}"
                ." ({$posterior->adt_nr_aditivo}) que ainda está válido."
                .' Cancele-o primeiro — do mais novo para o mais antigo — senão não há como saber a que valor o contrato deve voltar.',
        ]);
    }

    private function reverterQtd(ContratoAditivo $aditivo): void
    {
        $item = ContratoItem::lockForUpdate()->findOrFail($aditivo->adt_cti_id);

        $anterior = (float) $aditivo->adt_qtd_anterior;

        // Voltar para uma quantidade menor do que já foi pedido em ordens
        // deixaria o contrato estourado — o mesmo motivo que barra o aditivo.
        $pedida = $item->qtdPedida();
        if ($anterior < $pedida) {
            throw ValidationException::withMessages([
                'adt_justificativa_cancelamento' => 'Cancelar este aditivo devolveria a quantidade para '
                    .$this->formatar($anterior).', mas já há '.$this->formatar($pedida)
                    .' pedidos em ordens de fornecimento. Cancele as ordens antes.',
            ]);
        }

        $item->update(['cti_qtd' => $anterior]);
    }

    private function aditivarValor(Contrato $contrato, array $dados): array
    {
        $anterior = (float) $contrato->ctr_vl_total;
        $novo     = (float) $dados['adt_vl_novo'];

        if ($novo === $anterior) {
            throw ValidationException::withMessages([
                'adt_vl_novo' => 'O novo valor é igual ao valor atual do contrato — não há o que aditivar.',
            ]);
        }

        $contrato->update(['ctr_vl_total' => $novo]);

        return ['adt_vl_anterior' => $anterior, 'adt_vl_novo' => $novo];
    }

    private function aditivarPrazo(Contrato $contrato, array $dados): array
    {
        $anterior = $contrato->ctr_dt_fim;
        $nova     = $dados['adt_dt_fim_nova'];

        if ($anterior->toDateString() === $nova) {
            throw ValidationException::withMessages([
                'adt_dt_fim_nova' => 'A nova vigência é igual à atual — não há o que aditivar.',
            ]);
        }

        // Encurtar a vigência para antes do início deixaria o contrato inválido.
        if ($nova < $contrato->ctr_dt_inicio->toDateString()) {
            throw ValidationException::withMessages([
                'adt_dt_fim_nova' => 'A nova vigência não pode ser anterior ao início do contrato ('
                    .$contrato->ctr_dt_inicio->format('d/m/Y').').',
            ]);
        }

        $contrato->update(['ctr_dt_fim' => $nova]);

        return [
            'adt_dt_fim_anterior' => $anterior->toDateString(),
            'adt_dt_fim_nova'     => $nova,
        ];
    }

    private function aditivarObjeto(Contrato $contrato, array $dados): array
    {
        $anterior = $contrato->ctr_objeto;
        $novo     = $dados['adt_objeto_novo'];

        if ($novo === $anterior) {
            throw ValidationException::withMessages([
                'adt_objeto_novo' => 'O novo objeto é igual ao atual — não há o que aditivar.',
            ]);
        }

        $contrato->update(['ctr_objeto' => $novo]);

        return ['adt_objeto_anterior' => $anterior, 'adt_objeto_novo' => $novo];
    }

    private function aditivarQtd(Contrato $contrato, array $dados): array
    {
        $item = ContratoItem::where('cti_ctr_id', $contrato->ctr_id)
            ->lockForUpdate()
            ->findOrFail($dados['adt_cti_id']);

        $anterior = (float) $item->cti_qtd;
        $nova     = (float) $dados['adt_qtd_nova'];

        if ($nova === $anterior) {
            throw ValidationException::withMessages([
                'adt_qtd_nova' => 'A nova quantidade é igual à atual — não há o que aditivar.',
            ]);
        }

        // Reduzir abaixo do que já foi pedido em ordens deixaria o contrato
        // estourado retroativamente — saldo negativo sem ninguém ter feito nada.
        $pedida = $item->qtdPedida();
        if ($nova < $pedida) {
            throw ValidationException::withMessages([
                'adt_qtd_nova' => 'Já há '.$this->formatar($pedida).' pedidos em ordens de fornecimento para este item.'
                    .' A nova quantidade não pode ser menor que isso.',
            ]);
        }

        $item->update(['cti_qtd' => $nova]);

        return [
            'adt_cti_id'       => $item->cti_id,
            'adt_qtd_anterior' => $anterior,
            'adt_qtd_nova'     => $nova,
        ];
    }

    private function formatar(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}
