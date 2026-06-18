<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConceitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $selfId = $this->route('conceito')?->cnc_id;

        return [
            'cnc_sigla' => [
                'required', 'string', 'max:10',
                Rule::unique('cfg_conceito', 'cnc_sigla')->ignore($selfId, 'cnc_id'),
            ],
            'cnc_descricao'       => ['required', 'string', 'max:100'],
            'cnc_limite_inferior' => ['required', 'numeric', 'min:0'],
            'cnc_limite_superior' => ['required', 'numeric', 'gte:cnc_limite_inferior'],
            'cnc_peso'            => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnc_sigla.unique'             => 'Já existe um conceito com esta sigla.',
            'cnc_limite_superior.gte'      => 'O limite superior não pode ser menor que o limite inferior.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cnc_sigla'           => 'sigla',
            'cnc_descricao'       => 'descrição',
            'cnc_limite_inferior' => 'limite inferior',
            'cnc_limite_superior' => 'limite superior',
            'cnc_peso'            => 'peso',
        ];
    }
}
