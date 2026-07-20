<?php

namespace App\Http\Requests\Transporte;

use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;

        $nome = $this->input('tmo_nome');
        if (ParametroEntidade::current()->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $this->merge([
            'tmo_nome'     => $nome,
            'tmo_cpf'      => $this->filled('tmo_cpf') ? $strip($this->input('tmo_cpf')) : null,
            'tmo_telefone' => $this->filled('tmo_telefone') ? $strip($this->input('tmo_telefone')) : null,
            'tmo_fun_id'   => $this->input('tmo_fun_id') ?: null,
            'tmo_fl_ativo' => $this->boolean('tmo_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tmo_nome' => ['required', 'string', 'max:150'],
            'tmo_cpf'  => [
                'nullable', new Cpf,
                Rule::unique('edu_transporte_monitor', 'tmo_cpf')
                    ->whereNull('tmo_deleted_at')
                    ->ignore($this->route('monitor')?->tmo_id, 'tmo_id'),
            ],
            'tmo_dt_nascimento' => ['nullable', 'date', 'before:today'],
            'tmo_telefone'      => ['nullable', 'string', 'max:20'],
            'tmo_fun_id'        => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
            'tmo_fl_ativo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tmo_cpf.unique' => 'Já existe um monitor cadastrado com este CPF.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tmo_nome'          => 'nome',
            'tmo_cpf'           => 'CPF',
            'tmo_dt_nascimento' => 'data de nascimento',
            'tmo_telefone'      => 'telefone',
            'tmo_fun_id'        => 'vínculo funcional',
            'tmo_fl_ativo'      => 'situação',
        ];
    }
}
