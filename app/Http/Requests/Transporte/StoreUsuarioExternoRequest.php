<?php

namespace App\Http\Requests\Transporte;

use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;

        $nome = $this->input('tue_nome');
        if (ParametroEntidade::current()->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $this->merge([
            'tue_nome'     => $nome,
            'tue_cpf'      => $this->filled('tue_cpf') ? $strip($this->input('tue_cpf')) : null,
            'tue_telefone' => $this->filled('tue_telefone') ? $strip($this->input('tue_telefone')) : null,
            'tue_esc_id'   => $this->input('tue_esc_id') ?: null,
            'tue_fl_ativo' => $this->boolean('tue_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tue_nome' => ['required', 'string', 'max:150'],
            'tue_cpf'  => [
                'nullable', new Cpf,
                Rule::unique('edu_transporte_usuario', 'tue_cpf')
                    ->whereNull('tue_deleted_at')
                    ->ignore($this->route('usuario')?->tue_id, 'tue_id'),
            ],
            'tue_dt_nascimento' => ['nullable', 'date', 'before:today'],
            'tue_telefone'      => ['nullable', 'string', 'max:20'],
            'tue_esc_id'        => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tue_justificativa' => ['required', 'string', 'max:2000'],
            'tue_fl_ativo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tue_cpf.unique'             => 'Já existe um usuário do transporte cadastrado com este CPF.',
            'tue_justificativa.required' => 'Informe a justificativa de utilização do transporte escolar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tue_nome'          => 'nome',
            'tue_cpf'           => 'CPF',
            'tue_dt_nascimento' => 'data de nascimento',
            'tue_telefone'      => 'telefone',
            'tue_esc_id'        => 'instituição de ensino',
            'tue_justificativa' => 'justificativa de utilização',
            'tue_fl_ativo'      => 'situação',
        ];
    }
}
