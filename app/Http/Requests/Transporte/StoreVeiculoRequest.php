<?php

namespace App\Http\Requests\Transporte;

use App\Models\Transporte\Veiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $placa = strtoupper((string) $this->input('vei_placa', ''));
        $placa = preg_replace('/[^A-Z0-9]/', '', $placa);

        $this->merge([
            'vei_placa'           => $placa,
            'vei_renavam'         => $this->filled('vei_renavam') ? preg_replace('/\D/', '', $this->input('vei_renavam')) : null,
            'vei_fl_acessivel'    => $this->boolean('vei_fl_acessivel'),
            'vei_fl_terceirizado' => $this->boolean('vei_fl_terceirizado'),
            'vei_fl_ativo'        => $this->boolean('vei_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'vei_placa' => [
                'required', 'string',
                // Formato antigo (AAA9999) ou Mercosul (AAA9A99)
                'regex:/^[A-Z]{3}[0-9]{4}$|^[A-Z]{3}[0-9][A-Z][0-9]{2}$/',
                Rule::unique('edu_transporte_veiculo', 'vei_placa')
                    ->whereNull('vei_deleted_at')
                    ->ignore($this->route('veiculo')?->vei_id, 'vei_id'),
            ],
            'vei_renavam'         => ['nullable', 'digits_between:9,11'],
            'vei_tipo'            => ['required', Rule::in(array_keys(Veiculo::TIPOS))],
            'vei_capacidade'      => ['required', 'integer', 'min:1', 'max:200'],
            'vei_marca'           => ['nullable', 'string', 'max:60'],
            'vei_modelo'          => ['nullable', 'string', 'max:60'],
            'vei_ano_fabricacao'  => ['nullable', 'integer', 'min:1950', 'max:' . ((int) date('Y') + 1)],
            'vei_fl_acessivel'    => ['boolean'],
            'vei_fl_terceirizado' => ['boolean'],
            'vei_dt_licenciamento' => ['nullable', 'date'],
            'vei_dt_seguro'        => ['nullable', 'date'],
            'vei_dt_vistoria'      => ['nullable', 'date'],
            'vei_obs'              => ['nullable', 'string', 'max:2000'],
            'vei_fl_ativo'         => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'vei_placa.regex'  => 'Informe uma placa válida (AAA9999 ou AAA9A99).',
            'vei_placa.unique' => 'Já existe um veículo cadastrado com esta placa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'vei_placa'            => 'placa',
            'vei_renavam'          => 'RENAVAM',
            'vei_tipo'             => 'tipo de veículo',
            'vei_capacidade'       => 'capacidade',
            'vei_marca'            => 'marca',
            'vei_modelo'           => 'modelo',
            'vei_ano_fabricacao'   => 'ano de fabricação',
            'vei_dt_licenciamento' => 'validade do licenciamento',
            'vei_dt_seguro'        => 'validade do seguro',
            'vei_dt_vistoria'      => 'validade da vistoria',
            'vei_obs'              => 'observações',
            'vei_fl_ativo'         => 'situação',
        ];
    }
}
