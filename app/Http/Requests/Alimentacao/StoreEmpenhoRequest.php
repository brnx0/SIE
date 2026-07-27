<?php

namespace App\Http\Requests\Alimentacao;

use App\Models\Alimentacao\Empenho;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpenhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'emp_nr_empenho' => mb_strtoupper(trim((string) $this->input('emp_nr_empenho', '')), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        return [
            'emp_ctr_id'     => ['required', 'integer', 'exists:ali_contrato,ctr_id'],
            'emp_nr_empenho' => [
                'required', 'string', 'max:30',
                Rule::unique('ali_empenho', 'emp_nr_empenho')->whereNull('emp_deleted_at')->ignore($this->route('id'), 'emp_id'),
            ],
            'emp_dt'           => ['required', 'date'],
            'emp_vl_empenhado' => ['required', 'numeric', 'gt:0'],
            'emp_situacao'     => ['required', 'string', Rule::in(Empenho::SITUACOES)],
            'emp_obs'          => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'emp_nr_empenho.unique' => 'Já existe um empenho com este número.',
            'emp_vl_empenhado.gt'   => 'O valor empenhado deve ser maior que zero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'emp_ctr_id'       => 'contrato',
            'emp_nr_empenho'   => 'número do empenho',
            'emp_dt'           => 'data',
            'emp_vl_empenhado' => 'valor empenhado',
            'emp_situacao'     => 'situação',
            'emp_obs'          => 'observação',
        ];
    }
}
