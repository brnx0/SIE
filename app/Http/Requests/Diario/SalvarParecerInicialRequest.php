<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;

class SalvarParecerInicialRequest extends FormRequest
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
        // Sem unidade letiva: o parecer inicial não aceita vínculo com período.
        return [
            'dpr_esc_id'    => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dpr_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dpr_tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dpr_dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dpr_aln_id'    => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dpr_descricao' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
