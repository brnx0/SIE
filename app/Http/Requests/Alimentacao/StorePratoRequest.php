<?php

namespace App\Http\Requests\Alimentacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prt_nome'      => mb_strtoupper(trim((string) $this->input('prt_nome', '')), 'UTF-8'),
            'prt_fl_ativo'  => $this->boolean('prt_fl_ativo'),
        ]);
    }

    /** Nutrientes: todos opcionais, nunca negativos (o banco também tem CHECK). */
    private const NUTRIENTES = [
        'prt_nut_energia_kcal',
        'prt_nut_carboidrato_g',
        'prt_nut_acucares_g',
        'prt_nut_proteina_g',
        'prt_nut_gordura_total_g',
        'prt_nut_gordura_saturada_g',
        'prt_nut_gordura_trans_g',
        'prt_nut_fibra_g',
        'prt_nut_sodio_mg',
    ];

    public function rules(): array
    {
        $regras = [
            'prt_nome' => [
                'required', 'string', 'max:120',
                Rule::unique('ali_prato', 'prt_nome')->whereNull('prt_deleted_at')->ignore($this->route('id'), 'prt_id'),
            ],
            'prt_descricao'    => ['nullable', 'string'],
            'prt_modo_preparo' => ['nullable', 'string'],
            'prt_fl_ativo'     => ['boolean'],

            'prt_nut_porcao'        => ['nullable', 'numeric', 'gt:0'],
            'prt_nut_porcao_umd_id' => ['nullable', 'integer', 'exists:ali_unidade_medida,umd_id'],
            'prt_nut_observacao'    => ['nullable', 'string'],
        ];

        foreach (self::NUTRIENTES as $campo) {
            $regras[$campo] = ['nullable', 'numeric', 'min:0'];
        }

        return $regras;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Porção sem unidade (ou vice-versa) não diz nada: "250" de quê?
            if ($this->filled('prt_nut_porcao') && ! $this->filled('prt_nut_porcao_umd_id')) {
                $validator->errors()->add('prt_nut_porcao_umd_id', 'Informe a unidade da porção (ex.: 250 G).');
            }
            if ($this->filled('prt_nut_porcao_umd_id') && ! $this->filled('prt_nut_porcao')) {
                $validator->errors()->add('prt_nut_porcao', 'Informe o tamanho da porção.');
            }

            // Saturada e trans são parte da gordura total — maior que o total é erro
            // de digitação, e sairia impresso na tabela nutricional assim mesmo.
            $total = $this->input('prt_nut_gordura_total_g');
            if (is_numeric($total)) {
                foreach ([
                    'prt_nut_gordura_saturada_g' => 'saturada',
                    'prt_nut_gordura_trans_g'    => 'trans',
                ] as $campo => $nome) {
                    if (is_numeric($this->input($campo)) && (float) $this->input($campo) > (float) $total) {
                        $validator->errors()->add($campo, "A gordura {$nome} não pode ser maior que a gordura total.");
                    }
                }
            }

            // Açúcares são parte dos carboidratos.
            $carbo = $this->input('prt_nut_carboidrato_g');
            if (is_numeric($carbo) && is_numeric($this->input('prt_nut_acucares_g'))
                && (float) $this->input('prt_nut_acucares_g') > (float) $carbo) {
                $validator->errors()->add('prt_nut_acucares_g', 'Os açúcares não podem ser maiores que os carboidratos.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'prt_nome'         => 'nome',
            'prt_descricao'    => 'descrição',
            'prt_modo_preparo' => 'modo de preparo',
            'prt_fl_ativo'     => 'situação',
        ];
    }
}
