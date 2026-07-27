<?php

namespace App\Http\Requests\Alimentacao;

use App\Models\Alimentacao\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ctr_nr_contrato' => mb_strtoupper(trim((string) $this->input('ctr_nr_contrato', '')), 'UTF-8'),
            'ctr_modalidade'  => mb_strtoupper(trim((string) $this->input('ctr_modalidade', '')), 'UTF-8') ?: null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'ctr_frn_id'      => ['required', 'integer', 'exists:ali_fornecedor,frn_id'],
            'ctr_nr_contrato' => [
                'required', 'string', 'max:30',
                // Número se repete entre anos: o unique é por (número, ano).
                Rule::unique('ali_contrato', 'ctr_nr_contrato')
                    ->where(fn ($q) => $q->where('ctr_ano', $this->input('ctr_ano')))
                    ->whereNull('ctr_deleted_at')
                    ->ignore($id, 'ctr_id'),
            ],
            'ctr_ano'           => ['required', 'integer', 'min:2000', 'max:2099'],
            'ctr_objeto'        => ['required', 'string'],
            'ctr_modalidade'    => ['nullable', 'string', 'max:40'],
            'ctr_dt_assinatura' => ['nullable', 'date'],
            'ctr_dt_inicio'     => ['required', 'date'],
            'ctr_dt_fim'        => ['required', 'date', 'after_or_equal:ctr_dt_inicio'],
            'ctr_vl_total'      => ['required', 'numeric', 'gt:0'],
            'ctr_situacao'      => ['required', 'string', Rule::in(Contrato::SITUACOES)],
        ];
    }

    public function messages(): array
    {
        return [
            'ctr_nr_contrato.unique' => 'Já existe um contrato com este número neste ano.',
            'ctr_vl_total.gt'        => 'O valor do contrato deve ser maior que zero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ctr_frn_id'        => 'fornecedor',
            'ctr_nr_contrato'   => 'número do contrato',
            'ctr_ano'           => 'ano',
            'ctr_objeto'        => 'objeto',
            'ctr_modalidade'    => 'modalidade',
            'ctr_dt_assinatura' => 'data de assinatura',
            'ctr_dt_inicio'     => 'início da vigência',
            'ctr_dt_fim'        => 'fim da vigência',
            'ctr_vl_total'      => 'valor total',
            'ctr_situacao'      => 'situação',
        ];
    }
}
