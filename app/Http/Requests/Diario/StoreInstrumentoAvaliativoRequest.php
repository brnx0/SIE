<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstrumentoAvaliativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iav_fl_ativo' => $this->boolean('iav_fl_ativo'),
            'iav_nome'     => mb_strtoupper(trim($this->input('iav_nome', '')), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        return [
            'iav_nome' => [
                'required', 'string', 'max:100',
                Rule::unique('edu_instrumento_avaliativo', 'iav_nome')
                    ->ignore($this->route('instrumento')?->iav_id, 'iav_id'),
            ],
            'iav_fl_ativo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'iav_nome'     => 'nome',
            'iav_fl_ativo' => 'situação',
        ];
    }
}
