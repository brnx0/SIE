<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;

class SalvarAvaliacaoDescritivaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->hasRole('professor');
    }

    public function rules(): array
    {
        return [
            'dad_esc_id'    => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dad_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dad_tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dad_dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dad_uni_id'    => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dad_aln_id'    => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dad_descricao' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
