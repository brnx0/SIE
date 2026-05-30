<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\Unidade;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uni_anl_id'        => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'uni_tipo'          => ['required', 'string', Rule::in(array_keys(Unidade::LIMITES))],
            'uni_dt_inicio'     => ['required', 'date'],
            'uni_dt_fim'        => ['required', 'date', 'after_or_equal:uni_dt_inicio'],
            'uni_dias_extensao' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->count()) {
                return;
            }

            $anlId  = (int) $this->input('uni_anl_id');
            $tipo   = $this->input('uni_tipo');
            $selfId = $this->route('unidade')?->uni_id;

            $periodos = Unidade::where('uni_anl_id', $anlId)
                ->when($selfId, fn ($q) => $q->where('uni_id', '!=', $selfId))
                ->get();

            // Tipo deve ser consistente com os demais períodos do ano
            $tipoExistente = $periodos->first()?->uni_tipo;
            if ($tipoExistente && $tipoExistente !== $tipo) {
                $label = Unidade::LABELS[$tipoExistente] ?? $tipoExistente;
                $v->errors()->add('uni_tipo', "Este ano letivo já usa o tipo \"{$label}\". Não é possível misturar tipos.");
                return;
            }

            // Limite de períodos por tipo não deve ser excedido
            $limite = Unidade::LIMITES[$tipo] ?? 0;
            if ($periodos->count() >= $limite) {
                $v->errors()->add('uni_tipo', "O tipo selecionado permite no máximo {$limite} período(s). Limite atingido.");
                return;
            }

            // Datas não devem sobrepor outros períodos do mesmo ano
            $inicio = $this->input('uni_dt_inicio');
            $fim    = $this->input('uni_dt_fim');

            $overlap = $periodos->first(function (Unidade $u) use ($inicio, $fim) {
                return $u->uni_dt_inicio <= $fim && $u->uni_dt_fim >= $inicio;
            });

            if ($overlap) {
                $ord = Unidade::ORDINAL[$overlap->uni_numero] ?? "{$overlap->uni_numero}º";
                $v->errors()->add('uni_dt_inicio', "Datas sobrepõem o {$ord} período deste ano letivo.");
            }
        });
    }

    public function attributes(): array
    {
        return [
            'uni_anl_id'        => 'ano letivo',
            'uni_tipo'          => 'tipo de unidade',
            'uni_dt_inicio'     => 'data de início',
            'uni_dt_fim'        => 'data de fim',
            'uni_dias_extensao' => 'dias de extensão',
        ];
    }
}
