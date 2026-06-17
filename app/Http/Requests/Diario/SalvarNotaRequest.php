<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;

class SalvarNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->hasRole('professor'));
    }

    public function rules(): array
    {
        return [
            'nta_ava_id' => ['required', 'integer', 'exists:edu_diario_avaliacao,ava_id'],
            'nta_aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'nta_valor'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nta_valor' => 'nota',
        ];
    }
}
