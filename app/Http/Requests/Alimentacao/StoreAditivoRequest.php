<?php

namespace App\Http\Requests\Alimentacao;

use App\Models\Alimentacao\ContratoAditivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAditivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adt_nr_aditivo' => mb_strtoupper(trim((string) $this->input('adt_nr_aditivo', '')), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        $contratoId = $this->route('id');

        return [
            'adt_nr_aditivo' => [
                'required', 'string', 'max:30',
                Rule::unique('ali_contrato_aditivo', 'adt_nr_aditivo')
                    ->where(fn ($q) => $q->where('adt_ctr_id', $contratoId)),
            ],
            'adt_tipo'          => ['required', 'string', Rule::in(ContratoAditivo::TIPOS)],
            'adt_dt_assinatura' => ['required', 'date'],
            'adt_justificativa' => ['nullable', 'string'],

            // Cada tipo exige o campo do seu de/para — e só ele.
            'adt_vl_novo'     => ['required_if:adt_tipo,VALOR', 'nullable', 'numeric', 'gt:0'],
            'adt_dt_fim_nova' => ['required_if:adt_tipo,PRAZO', 'nullable', 'date'],
            'adt_objeto_novo' => ['required_if:adt_tipo,OBJETO', 'nullable', 'string'],
            'adt_cti_id'      => [
                'required_if:adt_tipo,QTD', 'nullable', 'integer',
                Rule::exists('ali_contrato_item', 'cti_id')->where(fn ($q) => $q->where('cti_ctr_id', $contratoId)),
            ],
            'adt_qtd_nova' => ['required_if:adt_tipo,QTD', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'adt_nr_aditivo.unique'    => 'Já existe um aditivo com este número neste contrato.',
            'adt_vl_novo.required_if'  => 'Informe o novo valor do contrato.',
            'adt_dt_fim_nova.required_if' => 'Informe a nova data de fim da vigência.',
            'adt_objeto_novo.required_if' => 'Informe o novo objeto do contrato.',
            'adt_cti_id.required_if'   => 'Escolha o item do contrato cuja quantidade será alterada.',
            'adt_cti_id.exists'        => 'O item escolhido não pertence a este contrato.',
            'adt_qtd_nova.required_if' => 'Informe a nova quantidade.',
        ];
    }

    public function attributes(): array
    {
        return [
            'adt_nr_aditivo'    => 'número do aditivo',
            'adt_tipo'          => 'tipo',
            'adt_dt_assinatura' => 'data de assinatura',
            'adt_justificativa' => 'justificativa',
            'adt_vl_novo'       => 'novo valor',
            'adt_dt_fim_nova'   => 'nova vigência',
            'adt_objeto_novo'   => 'novo objeto',
            'adt_cti_id'        => 'item do contrato',
            'adt_qtd_nova'      => 'nova quantidade',
        ];
    }
}
