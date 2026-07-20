<?php

namespace App\Http\Requests\Transporte;

use App\Models\Parametro\ParametroEntidade;
use App\Models\Transporte\Instituicao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstituicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nome = $this->input('tin_nome');
        if (ParametroEntidade::current()->par_fl_nome_escola_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $this->merge([
            'tin_nome'     => $nome,
            'tin_telefone' => $this->filled('tin_telefone') ? preg_replace('/\D/', '', $this->input('tin_telefone')) : null,
            'tin_esc_id'   => $this->input('tin_esc_id') ?: null,
            'tin_bai_id'   => $this->input('tin_bai_id') ?: null,
            'tin_mun_id'   => $this->input('tin_mun_id') ?: null,
            'tin_fl_ativo' => $this->boolean('tin_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tin_nome' => [
                'required', 'string', 'max:150',
                Rule::unique('edu_transporte_instituicao', 'tin_nome')
                    ->whereNull('tin_deleted_at')
                    ->ignore($this->route('instituicao')?->tin_id, 'tin_id'),
            ],
            'tin_esfera'    => ['required', Rule::in(array_keys(Instituicao::ESFERAS))],
            'tin_esc_id'    => ['nullable', 'integer', 'exists:edu_escola,esc_id', 'required_if:tin_esfera,municipal'],
            'tin_endereco'  => ['nullable', 'string', 'max:200'],
            'tin_bai_id'    => ['nullable', 'integer', 'exists:edu_bairro,bai_id'],
            'tin_mun_id'    => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'tin_telefone'  => ['nullable', 'string', 'max:20'],
            'tin_latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'tin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tin_fl_ativo'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tin_nome.unique'        => 'Já existe uma instituição cadastrada com este nome.',
            'tin_esc_id.required_if' => 'Instituição municipal deve ser vinculada a uma escola da rede.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tin_nome'      => 'nome',
            'tin_esfera'    => 'esfera',
            'tin_esc_id'    => 'escola da rede',
            'tin_endereco'  => 'endereço',
            'tin_bai_id'    => 'bairro',
            'tin_mun_id'    => 'município',
            'tin_telefone'  => 'telefone',
            'tin_latitude'  => 'latitude',
            'tin_longitude' => 'longitude',
            'tin_fl_ativo'  => 'situação',
        ];
    }
}
