<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSituacaoBloqueioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'sba_tas_cod' => [
                'required', 'integer', 'exists:edu_turma_aluno_situacao,tas_cod',
                Rule::unique('cfg_situacao_bloqueio', 'sba_tas_cod'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sba_tas_cod.unique' => 'Esta situação já está na lista de bloqueio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'sba_tas_cod' => 'situação',
        ];
    }
}
