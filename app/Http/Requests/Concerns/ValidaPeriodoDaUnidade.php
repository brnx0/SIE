<?php

namespace App\Http\Requests\Concerns;

use App\Models\Parametro\Unidade;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

/**
 * O plano vale para o período (unidade/trimestre) escolhido, então as datas de início e
 * fim precisam cair dentro dele. O frontend já limita o seletor de data, mas a regra vive
 * aqui: é o backend que garante quando a requisição não vem da tela.
 */
trait ValidaPeriodoDaUnidade
{
    protected function validarDatasNaUnidade(
        Validator $v,
        int $uniId,
        ?string $dtInicio,
        ?string $dtFim,
        string $campoInicio,
        string $campoFim,
    ): void {
        // Sem unidade ou sem data, as regras de `required`/`date` já reclamaram.
        if (! $uniId || ! $dtInicio || ! $dtFim) {
            return;
        }

        $unidade = Unidade::find($uniId);
        if (! $unidade || ! $unidade->uni_dt_inicio || ! $unidade->uni_dt_fim) {
            return;
        }

        $ini = Carbon::parse($unidade->uni_dt_inicio)->startOfDay();
        $fim = Carbon::parse($unidade->uni_dt_fim)->startOfDay();

        $periodo = sprintf(
            '%s a %s',
            $ini->format('d/m/Y'),
            $fim->format('d/m/Y'),
        );

        foreach ([$campoInicio => $dtInicio, $campoFim => $dtFim] as $campo => $valor) {
            $data = Carbon::parse($valor)->startOfDay();

            if ($data->lt($ini) || $data->gt($fim)) {
                $v->errors()->add($campo, "A data precisa estar dentro do período selecionado ({$periodo}).");
            }
        }
    }
}
