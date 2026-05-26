<?php

namespace App\Http\Requests\Serie;

use Illuminate\Foundation\Http\FormRequest;

class StoreSerieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ser_fl_ativo' => $this->boolean('ser_fl_ativo'),
            'ser_nome'     => mb_strtoupper($this->input('ser_nome', ''), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        return [
            'seg_id'                  => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'ser_cd_referencia'       => ['nullable', 'string', 'max:20'],
            'ser_nome'                => ['required', 'string', 'max:100'],
            'ser_carga_horaria'       => ['nullable', 'integer', 'min:0'],
            'ser_qt_aulas_semestrais' => ['nullable', 'integer', 'min:0'],
            'ser_qt_aulas_anuais'     => ['nullable', 'integer', 'min:0'],
            'ser_idade'               => ['required', 'integer', 'min:0'],
            'ser_serie_equivalente'   => ['nullable', 'string', 'max:100'],
            'ser_nr_ordenacao'        => ['nullable', 'integer'],
            'ser_ordem_no_segmento'   => ['required', 'integer'],
            'ser_fl_ativo'            => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'seg_id'                  => 'segmento',
            'ser_cd_referencia'       => 'código de referência',
            'ser_nome'                => 'nome da série',
            'ser_carga_horaria'       => 'carga horária',
            'ser_qt_aulas_semestrais' => 'total de aulas semestrais',
            'ser_qt_aulas_anuais'     => 'total de aulas anuais',
            'ser_idade'               => 'idade na série',
            'ser_serie_equivalente'   => 'série equivalente',
            'ser_nr_ordenacao'        => 'nº para ordenação',
            'ser_ordem_no_segmento'   => 'ordem da série no segmento',
        ];
    }
}
