<?php

namespace App\Http\Requests\Alimentacao;

use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'frn_razao_social'            => mb_strtoupper(trim((string) $this->input('frn_razao_social', '')), 'UTF-8'),
            'frn_nome_fantasia'           => mb_strtoupper(trim((string) $this->input('frn_nome_fantasia', '')), 'UTF-8') ?: null,
            'frn_logradouro'              => mb_strtoupper(trim((string) $this->input('frn_logradouro', '')), 'UTF-8') ?: null,
            'frn_complemento'             => mb_strtoupper(trim((string) $this->input('frn_complemento', '')), 'UTF-8') ?: null,
            // Documentos e CEP são gravados sem máscara; a máscara é da tela.
            // CNPJ pode ser alfanumérico (novo formato jul/2026) — soDigitos()
            // comeria as letras e transformaria 12ABC345000195 em 12345000195.
            'frn_cnpj'                    => $this->soAlfanumerico($this->input('frn_cnpj')),
            'frn_cpf'                     => $this->soDigitos($this->input('frn_cpf')),
            'frn_cep'                     => $this->soDigitos($this->input('frn_cep')),
            'frn_fone'                    => $this->soDigitos($this->input('frn_fone')),
            'frn_fl_agricultura_familiar' => $this->boolean('frn_fl_agricultura_familiar'),
            'frn_fl_ativo'                => $this->boolean('frn_fl_ativo'),
        ]);
    }

    private function soDigitos($valor): ?string
    {
        $limpo = preg_replace('/\D/', '', (string) $valor);

        return $limpo === '' ? null : $limpo;
    }

    /** Tira a máscara preservando letras — o CNPJ alfanumérico tem [0-9A-Z]. */
    private function soAlfanumerico($valor): ?string
    {
        $limpo = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));

        return $limpo === '' ? null : $limpo;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            // 'digits:14' rejeitaria o CNPJ alfanumérico; o formato exato
            // (12 alfanuméricos + 2 dígitos de DV) quem cobra é a Rule Cnpj.
            'frn_cnpj' => [
                'nullable', 'string', 'size:14', new Cnpj,
                Rule::unique('ali_fornecedor', 'frn_cnpj')->whereNull('frn_deleted_at')->ignore($id, 'frn_id'),
            ],
            'frn_cpf' => [
                'nullable', 'digits:11', new Cpf,
                Rule::unique('ali_fornecedor', 'frn_cpf')->whereNull('frn_deleted_at')->ignore($id, 'frn_id'),
            ],
            'frn_razao_social'            => ['required', 'string', 'max:150'],
            'frn_nome_fantasia'           => ['nullable', 'string', 'max:150'],
            'frn_fl_agricultura_familiar' => ['boolean'],
            'frn_email'                   => ['nullable', 'email', 'max:120'],
            // Fixo (10) ou celular (11), gravado só com dígitos — a máscara é da tela.
            'frn_fone'                    => ['nullable', 'digits_between:10,11'],
            'frn_cep'                     => ['nullable', 'digits:8'],
            'frn_logradouro'              => ['nullable', 'string', 'max:150'],
            'frn_numero'                  => ['nullable', 'string', 'max:10'],
            'frn_complemento'             => ['nullable', 'string', 'max:60'],
            'frn_mun_id'                  => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'frn_bai_id'                  => ['nullable', 'integer', 'exists:edu_bairro,bai_id'],
            'frn_fl_ativo'                => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // O banco tem CHECK exigindo ao menos um documento; sem esta regra o
            // usuário levaria erro 500 do Postgres em vez de mensagem na tela.
            if (! $this->filled('frn_cnpj') && ! $this->filled('frn_cpf')) {
                $validator->errors()->add(
                    'frn_cnpj',
                    'Informe o CNPJ ou o CPF do fornecedor. Fornecedor da agricultura familiar costuma ser pessoa física (CPF).',
                );
            }

            // Bairro de um município diferente do informado seria endereço inválido.
            if ($this->filled('frn_bai_id') && $this->filled('frn_mun_id')) {
                $pertence = \App\Models\Escola\Bairro::where('bai_id', $this->input('frn_bai_id'))
                    ->where('bai_mun_id', $this->input('frn_mun_id'))
                    ->exists();

                if (! $pertence) {
                    $validator->errors()->add('frn_bai_id', 'O bairro selecionado não pertence ao município informado.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'frn_cnpj'                    => 'CNPJ',
            'frn_cpf'                     => 'CPF',
            'frn_razao_social'            => 'razão social',
            'frn_nome_fantasia'           => 'nome fantasia',
            'frn_fl_agricultura_familiar' => 'agricultura familiar',
            'frn_email'                   => 'e-mail',
            'frn_fone'                    => 'telefone',
            'frn_cep'                     => 'CEP',
            'frn_logradouro'              => 'logradouro',
            'frn_numero'                  => 'número',
            'frn_complemento'             => 'complemento',
            'frn_mun_id'                  => 'município',
            'frn_bai_id'                  => 'bairro',
            'frn_fl_ativo'                => 'situação',
        ];
    }
}
