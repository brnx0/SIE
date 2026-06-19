<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;

class StoreJustificativaFaltaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['secretaria_escola', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'jfa_anl_id'     => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'jfa_esc_id'     => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'jfa_tur_id'     => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'jfa_aln_id'     => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'jfa_mbf_id'     => ['required', 'integer', 'exists:cfg_motivo_baixa_frequencia,mbf_id'],
            'jfa_dt_inicio'  => ['required', 'date'],
            'jfa_dt_fim'     => ['required', 'date', 'after_or_equal:jfa_dt_inicio'],
            'jfa_observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'jfa_anl_id'     => 'ano letivo',
            'jfa_esc_id'     => 'escola',
            'jfa_tur_id'     => 'turma',
            'jfa_aln_id'     => 'aluno',
            'jfa_mbf_id'     => 'motivo',
            'jfa_dt_inicio'  => 'data início',
            'jfa_dt_fim'     => 'data fim',
            'jfa_observacao' => 'observação',
        ];
    }
}
