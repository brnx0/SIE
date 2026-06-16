<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\AnoLetivo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiaNaoLetivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $selfId = $this->route('diaNaoLetivo')?->dnl_id;

        return [
            'dnl_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dnl_dt_dia'    => [
                'required', 'date',
                Rule::unique('cfg_dia_nao_letivo', 'dnl_dt_dia')
                    ->where('dnl_anl_id', $this->input('dnl_anl_id'))
                    ->ignore($selfId, 'dnl_id'),
            ],
            'dnl_dt_fim'    => ['nullable', 'date', 'after_or_equal:dnl_dt_dia'],
            'dnl_descricao' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->count()) {
                return;
            }

            $ano = AnoLetivo::find((int) $this->input('dnl_anl_id'));
            if (! $ano) {
                return;
            }

            // O dia deve cair dentro do período do ano letivo
            $inicioAno = $ano->anl_dt_inicio_ano->format('Y-m-d');
            $fimAno    = $ano->anl_dt_fim->format('Y-m-d');

            $dia = $this->input('dnl_dt_dia');
            if ($dia < $inicioAno || $dia > $fimAno) {
                $v->errors()->add('dnl_dt_dia', 'A data deve estar dentro do período do ano letivo selecionado.');
            }

            // Data fim (opcional) também precisa cair dentro do ano letivo
            $fim = $this->input('dnl_dt_fim');
            if ($fim && ($fim < $inicioAno || $fim > $fimAno)) {
                $v->errors()->add('dnl_dt_fim', 'A data fim deve estar dentro do período do ano letivo selecionado.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'dnl_dt_dia.unique' => 'Este dia já está cadastrado como não letivo neste ano letivo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dnl_anl_id'    => 'ano letivo',
            'dnl_dt_dia'    => 'data',
            'dnl_dt_fim'    => 'data fim',
            'dnl_descricao' => 'descrição',
        ];
    }
}
