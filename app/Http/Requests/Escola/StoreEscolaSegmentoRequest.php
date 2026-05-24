<?php

namespace App\Http\Requests\Escola;

use Illuminate\Foundation\Http\FormRequest;

class StoreEscolaSegmentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'esg_fl_ativo' => $this->boolean('esg_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'seg_id'        => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'anl_id_inicio' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'anl_id_fim'    => ['nullable', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'ser_id_inicio' => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'ser_id_fim'    => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'esg_fl_ativo'  => ['boolean'],
            'esg_motivo'    => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'seg_id'        => 'segmento',
            'anl_id_inicio' => 'ano letivo inicial',
            'anl_id_fim'    => 'ano letivo final',
            'ser_id_inicio' => 'ano escolar inicial',
            'ser_id_fim'    => 'ano escolar final',
            'esg_motivo'    => 'motivo',
        ];
    }
}
