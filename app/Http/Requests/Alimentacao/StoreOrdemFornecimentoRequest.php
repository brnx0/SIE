<?php

namespace App\Http\Requests\Alimentacao;

use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\Empenho;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdemFornecimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ofn_nr_ordem' => mb_strtoupper(trim((string) $this->input('ofn_nr_ordem', '')), 'UTF-8'),
            'ofn_emp_id'   => $this->input('ofn_emp_id') ?: null,
        ]);

        // A situação sai do recebimento (AceitaRecebimento::atualizarSituacaoDaOrdem)
        // e do cancelamento — nunca do cliente. Aceitá-la aqui deixaria marcar
        // ENTREGUE sem nenhuma entrega, e o próximo aceite sobrescreveria.
        $this->request->remove('ofn_situacao');
    }

    public function rules(): array
    {
        return [
            'ofn_ctr_id'   => ['required', 'integer', 'exists:ali_contrato,ctr_id'],
            'ofn_emp_id'   => ['nullable', 'integer', 'exists:ali_empenho,emp_id'],
            'ofn_nr_ordem' => [
                'required', 'string', 'max:30',
                Rule::unique('ali_ordem_fornecimento', 'ofn_nr_ordem')->whereNull('ofn_deleted_at')->ignore($this->route('id'), 'ofn_id'),
            ],
            'ofn_dt_emissao'          => ['required', 'date'],
            'ofn_dt_entrega_prevista' => ['nullable', 'date', 'after_or_equal:ofn_dt_emissao'],
            'ofn_obs'                 => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $contrato = Contrato::find($this->input('ofn_ctr_id'));

            // Emitir ordem em contrato encerrado/suspenso é compra sem cobertura
            // contratual — o sistema não deve deixar acontecer por descuido.
            if ($contrato && ! $contrato->podeEmitirOrdem() && $this->isMethod('POST')) {
                $validator->errors()->add(
                    'ofn_ctr_id',
                    "O contrato {$contrato->ctr_nr_contrato}/{$contrato->ctr_ano} está {$contrato->ctr_situacao}."
                    .' Só contrato VIGENTE aceita novas ordens de fornecimento.',
                );
            }

            // Data de emissão fora da vigência: a ordem não estaria amparada.
            if ($contrato && $this->filled('ofn_dt_emissao')) {
                $emissao = $this->input('ofn_dt_emissao');
                if ($emissao < $contrato->ctr_dt_inicio->toDateString() || $emissao > $contrato->ctr_dt_fim->toDateString()) {
                    $validator->errors()->add(
                        'ofn_dt_emissao',
                        'A emissão está fora da vigência do contrato ('
                        .$contrato->ctr_dt_inicio->format('d/m/Y').' a '.$contrato->ctr_dt_fim->format('d/m/Y').').',
                    );
                }
            }

            // Empenho de outro contrato pagaria despesa que não é dele.
            if ($this->filled('ofn_emp_id')) {
                $empenho = Empenho::find($this->input('ofn_emp_id'));

                if ($empenho && $empenho->emp_ctr_id !== (int) $this->input('ofn_ctr_id')) {
                    $validator->errors()->add('ofn_emp_id', 'O empenho escolhido pertence a outro contrato.');
                }

                if ($empenho && $empenho->emp_situacao === Empenho::SITUACAO_CANCELADO) {
                    $validator->errors()->add('ofn_emp_id', 'Este empenho está cancelado e não pode custear ordens.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ofn_nr_ordem.unique' => 'Já existe uma ordem de fornecimento com este número.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ofn_ctr_id'              => 'contrato',
            'ofn_emp_id'              => 'empenho',
            'ofn_nr_ordem'            => 'número da ordem',
            'ofn_dt_emissao'          => 'data de emissão',
            'ofn_dt_entrega_prevista' => 'entrega prevista',
            'ofn_situacao'            => 'situação',
            'ofn_obs'                 => 'observação',
        ];
    }
}
