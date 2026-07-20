<?php

namespace App\Http\Requests\Transporte;

use App\Models\Parametro\ParametroEntidade;
use App\Models\Transporte\Motorista;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMotoristaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;

        $nome = $this->input('mot_nome');
        if (ParametroEntidade::current()->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $this->merge([
            'mot_nome'          => $nome,
            'mot_cpf'           => $this->filled('mot_cpf') ? $strip($this->input('mot_cpf')) : null,
            'mot_telefone'      => $this->filled('mot_telefone') ? $strip($this->input('mot_telefone')) : null,
            'mot_cnh_numero'    => $this->filled('mot_cnh_numero') ? $strip($this->input('mot_cnh_numero')) : null,
            'mot_cnh_categoria' => strtoupper((string) $this->input('mot_cnh_categoria', '')),
            'mot_fun_id'        => $this->input('mot_fun_id') ?: null,
            'mot_fl_ativo'      => $this->boolean('mot_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'mot_nome' => ['required', 'string', 'max:150'],
            'mot_cpf'  => [
                'nullable', new Cpf,
                Rule::unique('edu_transporte_motorista', 'mot_cpf')
                    ->whereNull('mot_deleted_at')
                    ->ignore($this->route('motorista')?->mot_id, 'mot_id'),
            ],
            'mot_dt_nascimento'   => ['nullable', 'date', 'before:today'],
            'mot_telefone'        => ['nullable', 'string', 'max:20'],
            'mot_fun_id'          => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
            'mot_cnh_numero'      => ['required', 'digits_between:9,11'],
            'mot_cnh_categoria'   => ['required', Rule::in(Motorista::CNH_CATEGORIAS)],
            'mot_cnh_dt_validade' => ['required', 'date'],
            'mot_fl_ativo'        => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mot_cpf.unique' => 'Já existe um motorista cadastrado com este CPF.',
        ];
    }

    public function attributes(): array
    {
        return [
            'mot_nome'            => 'nome',
            'mot_cpf'             => 'CPF',
            'mot_dt_nascimento'   => 'data de nascimento',
            'mot_telefone'        => 'telefone',
            'mot_fun_id'          => 'vínculo funcional',
            'mot_cnh_numero'      => 'número da CNH',
            'mot_cnh_categoria'   => 'categoria da CNH',
            'mot_cnh_dt_validade' => 'validade da CNH',
            'mot_fl_ativo'        => 'situação',
        ];
    }
}
