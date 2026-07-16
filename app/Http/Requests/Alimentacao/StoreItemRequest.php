<?php

namespace App\Http\Requests\Alimentacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'itm_nome'                    => mb_strtoupper(trim((string) $this->input('itm_nome', '')), 'UTF-8'),
            'itm_cd_item'                 => trim((string) $this->input('itm_cd_item', '')) ?: null,
            'itm_fl_perecivel'            => $this->boolean('itm_fl_perecivel'),
            'itm_fl_agricultura_familiar' => $this->boolean('itm_fl_agricultura_familiar'),
            'itm_fl_ativo'                => $this->boolean('itm_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'itm_tpi_id' => ['required', 'integer', 'exists:ali_tipo_item,tpi_id'],
            'itm_cat_id' => ['required', 'integer', 'exists:ali_categoria_alimento,cat_id'],
            'itm_umd_id' => ['required', 'integer', 'exists:ali_unidade_medida,umd_id'],
            'itm_cd_item' => [
                'nullable', 'string', 'max:20',
                Rule::unique('ali_item', 'itm_cd_item')->whereNull('itm_deleted_at')->ignore($id, 'itm_id'),
            ],
            'itm_nome' => [
                'required', 'string', 'max:120',
                Rule::unique('ali_item', 'itm_nome')->whereNull('itm_deleted_at')->ignore($id, 'itm_id'),
            ],
            'itm_especificacao'           => ['nullable', 'string'],
            'itm_fl_perecivel'            => ['boolean'],
            'itm_fl_agricultura_familiar' => ['boolean'],
            // Só faz sentido para perecível — a regra condicional está em withValidator.
            'itm_validade_dias'           => ['nullable', 'integer', 'min:1', 'max:3650'],
            'itm_estoque_minimo'          => ['nullable', 'numeric', 'min:0'],
            'itm_fl_ativo'                => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Prazo de validade em item não perecível não é usado por nada
            // (o relatório de vencimento olha só perecível) — evita dado morto.
            if (! $this->boolean('itm_fl_perecivel') && $this->filled('itm_validade_dias')) {
                $validator->errors()->add(
                    'itm_validade_dias',
                    'O prazo de validade só se aplica a item perecível. Marque "Perecível" ou deixe o prazo em branco.',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'itm_tpi_id'                  => 'tipo de item',
            'itm_cat_id'                  => 'categoria',
            'itm_umd_id'                  => 'unidade de medida',
            'itm_cd_item'                 => 'código',
            'itm_nome'                    => 'nome',
            'itm_especificacao'           => 'especificação técnica',
            'itm_fl_perecivel'            => 'perecível',
            'itm_fl_agricultura_familiar' => 'agricultura familiar',
            'itm_validade_dias'           => 'prazo de validade (dias)',
            'itm_estoque_minimo'          => 'estoque mínimo',
            'itm_fl_ativo'                => 'situação',
        ];
    }
}
