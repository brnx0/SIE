<?php

namespace App\Http\Requests\Disciplina;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dis_fl_ativo'       => $this->boolean('dis_fl_ativo'),
            'dis_nome_mec'       => mb_strtoupper($this->input('dis_nome_mec', ''), 'UTF-8'),
            'dis_nome'           => mb_strtoupper($this->input('dis_nome', ''), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        return [
            'arc_id'             => ['required', 'integer', 'exists:edu_area_conhecimento,arc_id'],
            'dis_cod_ref'        => ['nullable', 'integer', 'min:0'],
            'dis_nome_mec'       => ['required', 'string', 'max:100'],
            'dis_nome'           => [
                'required', 'string', 'max:100',
                Rule::unique('edu_disciplina', 'dis_nome')
                    ->whereNull('dis_deleted_at')
                    ->ignore($this->route('disciplina')?->dis_id, 'dis_id'),
            ],
            'dis_sigla'          => ['nullable', 'string', 'max:20'],
            'dis_fl_ativo'       => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'arc_id'             => 'área do conhecimento',
            'dis_cod_ref'        => 'código de referência',
            'dis_nome_mec'       => 'nome (MEC)',
            'dis_nome'           => 'nome reduzido',
            'dis_sigla'          => 'sigla',
            'dis_fl_ativo'       => 'situação',
        ];
    }
}
