<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSabadoLetivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sbl_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'sbl_dt_sabado' => [
                'required',
                'date',
                Rule::unique('cfg_sabado_letivo', 'sbl_dt_sabado')
                    ->where('sbl_anl_id', $this->input('sbl_anl_id')),
            ],
            'sbl_dia_semana' => ['required', 'integer', 'between:1,5'],
            'escolas_excluidas'   => ['array'],
            'escolas_excluidas.*' => ['integer', 'exists:edu_escola,esc_id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $date = $this->input('sbl_dt_sabado');
            if ($date && date('N', strtotime($date)) !== '6') {
                $v->errors()->add('sbl_dt_sabado', 'A data deve ser um sábado.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'sbl_anl_id'     => 'ano letivo',
            'sbl_dt_sabado'  => 'data do sábado',
            'sbl_dia_semana' => 'dia espelhado',
        ];
    }

    public function messages(): array
    {
        return [
            'sbl_dt_sabado.unique' => 'Esta data já está cadastrada para o ano letivo selecionado.',
        ];
    }
}
