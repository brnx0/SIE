<?php

namespace App\Http\Requests\Serie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSerieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ser_fl_ativo'  => $this->boolean('ser_fl_ativo'),
            'ser_fl_multi'  => $this->boolean('ser_fl_multi'),
            'ser_fl_progressao_auto' => $this->boolean('ser_fl_progressao_auto'),
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
            'ser_fl_multi'            => ['boolean'],
            'ser_fl_progressao_auto'  => ['boolean'],
            'ser_tipo_avaliacao'      => ['nullable', 'array'],
            'ser_tipo_avaliacao.*'    => ['string', Rule::in(['diagnostico', 'conceitual', 'numerica', 'descritiva'])],
            'ser_tipo_avaliacao_descritiva' => [
                Rule::when(
                    fn () => is_array($this->input('ser_tipo_avaliacao')) && in_array('descritiva', $this->input('ser_tipo_avaliacao')),
                    ['required'],
                    ['nullable']
                ),
                'string',
                Rule::in(['por_aluno', 'por_disciplina']),
            ],
            'ser_promo_ser_id_1' => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'ser_promo_ser_id_2' => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'ser_cons_ser_id_1'  => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'ser_cons_ser_id_2'  => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
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
            'ser_tipo_avaliacao'      => 'tipo de avaliação',
            'ser_tipo_avaliacao_descritiva' => 'tipo de avaliação descritiva',
        ];
    }
}
