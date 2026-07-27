<?php

namespace App\Http\Requests\Alimentacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnidadeMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'umd_sigla'          => mb_strtoupper(trim((string) $this->input('umd_sigla', '')), 'UTF-8'),
            'umd_nome'           => mb_strtoupper(trim((string) $this->input('umd_nome', '')), 'UTF-8'),
            'umd_fl_fracionavel' => $this->boolean('umd_fl_fracionavel'),
            'umd_fl_ativo'       => $this->boolean('umd_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'umd_sigla' => [
                'required', 'string', 'max:10',
                // Unique case-insensitive: o índice no banco é sobre UPPER(umd_sigla).
                Rule::unique('ali_unidade_medida', 'umd_sigla')->ignore($this->route('id'), 'umd_id'),
            ],
            'umd_nome'           => ['required', 'string', 'max:60'],
            'umd_fl_fracionavel' => ['boolean'],
            'umd_fl_ativo'       => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'umd_sigla'          => 'sigla',
            'umd_nome'           => 'nome',
            'umd_fl_fracionavel' => 'aceita fração',
            'umd_fl_ativo'       => 'situação',
        ];
    }
}
