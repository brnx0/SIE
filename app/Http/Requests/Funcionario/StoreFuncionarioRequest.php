<?php

namespace App\Http\Requests\Funcionario;

use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuncionarioRequest extends FormRequest
{
    protected ?ParametroEntidade $parametros = null;

    protected function parametros(): ParametroEntidade
    {
        return $this->parametros ??= ParametroEntidade::current();
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;
        $params = $this->parametros();

        $nome = $this->input('fun_nome');
        if ($params->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $this->merge([
            'fun_nome' => $nome,
            'fun_cpf' => $this->filled('fun_cpf') ? $strip($this->input('fun_cpf')) : null,
            'fun_cep' => $this->filled('fun_cep') ? $strip($this->input('fun_cep')) : null,
            'fun_telefone' => $this->filled('fun_telefone') ? $strip($this->input('fun_telefone')) : null,
            'fun_celular' => $this->filled('fun_celular') ? $strip($this->input('fun_celular')) : null,
            'fun_cd_censo' => $this->filled('fun_cd_censo') ? $strip($this->input('fun_cd_censo')) : null,
            'fun_uf' => $this->filled('fun_uf') ? strtoupper($this->input('fun_uf')) : null,
            'fun_rg_uf' => $this->filled('fun_rg_uf') ? strtoupper($this->input('fun_rg_uf')) : null,
        ]);
    }

    public function rules(): array
    {
        $funId = $this->route('funcionario')?->fun_id;

        return [
            // Dados pessoais
            'fun_nome' => ['required', 'string', 'max:100'],
            'fun_dt_nascimento' => ['required', 'date', 'before:today'],
            'fun_sexo' => ['required', Rule::in(['M', 'F'])],
            'fun_cor_raca' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
            'fun_nacionalidade' => ['required', 'string', 'max:60'],
            'fun_pais_origem' => ['required', 'string', 'max:60'],
            'fun_mun_id_nasc' => ['required', 'integer', 'exists:edu_municipio,mun_id'],
            'fun_cpf' => ['required', 'digits:11', new Cpf, Rule::unique('edu_funcionario', 'fun_cpf')->ignore($funId, 'fun_id')->whereNull('fun_deleted_at')],
            'fun_religiao' => ['nullable', 'string', 'max:60'],
            'fun_escolaridade' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8])],
            'fun_estado_civil' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'fun_povo_indigena' => ['nullable', 'string', 'max:60'],
            'fun_cd_censo' => ['nullable', 'digits:12', Rule::unique('edu_funcionario', 'fun_cd_censo')->ignore($funId, 'fun_id')->whereNull('fun_deleted_at')],

            // Documentação
            'fun_rg_numero' => ['nullable', 'string', 'max:20'],
            'fun_rg_dt_emissao' => ['nullable', 'date'],
            'fun_rg_uf' => ['nullable', 'string', 'size:2'],
            'fun_rg_orgao_emissor' => ['nullable', 'string', 'max:20'],
            'fun_certidao_modelo' => ['nullable', 'string', 'max:30'],
            'fun_certidao_tipo' => ['nullable', 'string', 'max:30'],
            'fun_certidao_dt_emissao' => ['nullable', 'date'],
            'fun_certidao_numero' => ['nullable', 'string', 'max:32'],
            'fun_certidao_livro' => ['nullable', 'string', 'max:10'],
            'fun_certidao_pagina' => ['nullable', 'string', 'max:10'],
            'fun_certidao_mun_id' => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'fun_certidao_cartorio' => ['nullable', 'string', 'max:100'],
            'fun_ctps_numero' => ['nullable', 'string', 'max:20'],
            'fun_ctps_serie' => ['nullable', 'string', 'max:10'],
            'fun_pis_pasep' => ['nullable', 'string', 'max:20'],
            'fun_titulo_eleitor' => ['nullable', 'string', 'max:15'],
            'fun_titulo_zona' => ['nullable', 'string', 'max:5'],
            'fun_titulo_secao' => ['nullable', 'string', 'max:5'],
            'fun_certificado_reservista' => ['nullable', 'string', 'max:20'],

            // Endereço
            'fun_cep' => ['nullable', 'digits:8'],
            'fun_logradouro' => ['nullable', 'string', 'max:150'],
            'fun_numero' => ['nullable', 'string', 'max:10'],
            'fun_complemento' => ['nullable', 'string', 'max:100'],
            'fun_bairro' => ['nullable', 'string', 'max:100'],
            'fun_cidade' => ['nullable', 'string', 'max:100'],
            'fun_uf' => ['nullable', 'string', 'size:2'],

            // Contato
            'fun_telefone' => ['nullable', 'digits_between:10,11'],
            'fun_celular' => ['nullable', 'digits_between:10,11'],
            'fun_email' => ['nullable', 'email', 'max:150'],

            'fun_fl_usa_transporte' => ['boolean'],
            'fun_transporte_tipo' => ['nullable', 'string', 'max:30'],

            'fun_foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'fun_fl_ativo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fun_nome' => 'nome completo',
            'fun_dt_nascimento' => 'data de nascimento',
            'fun_sexo' => 'sexo',
            'fun_cor_raca' => 'etnia / cor / raça',
            'fun_nacionalidade' => 'nacionalidade',
            'fun_pais_origem' => 'país de origem',
            'fun_mun_id_nasc' => 'naturalidade (município)',
            'fun_cpf' => 'CPF',
            'fun_religiao' => 'religião',
            'fun_escolaridade' => 'escolaridade',
            'fun_estado_civil' => 'estado civil',
            'fun_povo_indigena' => 'povo indígena',
            'fun_cd_censo' => 'código CENSO',
            'fun_rg_numero' => 'número do RG',
            'fun_rg_dt_emissao' => 'data de emissão do RG',
            'fun_rg_uf' => 'UF do RG',
            'fun_rg_orgao_emissor' => 'órgão emissor do RG',
            'fun_cep' => 'CEP',
            'fun_logradouro' => 'logradouro',
            'fun_numero' => 'número',
            'fun_complemento' => 'complemento',
            'fun_bairro' => 'bairro',
            'fun_cidade' => 'cidade',
            'fun_uf' => 'UF',
            'fun_telefone' => 'telefone',
            'fun_celular' => 'celular',
            'fun_email' => 'e-mail',
            'fun_foto' => 'foto',
        ];
    }
}
