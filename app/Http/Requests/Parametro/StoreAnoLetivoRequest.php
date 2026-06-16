<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnoLetivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('anoLetivo')?->anl_id;

        return [
            'anl_ano' => [
                'required', 'integer', 'between:1980,2999',
                Rule::unique('cfg_ano_letivo', 'anl_ano')->ignore($id, 'anl_id')->whereNull('anl_deleted_at'),
            ],
            'anl_dt_inicio_ano' => ['required', 'date'],
            'anl_dt_fim' => ['required', 'date', 'after:anl_dt_inicio_ano'],
            'anl_dt_corte' => ['required', 'date'],
            'anl_dt_censo' => ['nullable', 'date'],
            'anl_fl_em_exercicio' => ['boolean'],
            'anl_fl_progressao_parcial' => ['boolean'],
            'anl_fl_aprovacao_conselho_freq' => ['boolean'],
            'anl_frequencia_minima' => ['required', 'numeric', 'between:0,100'],
            'anl_media_geral' => ['required', 'numeric', 'between:0,10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ano = (int) $this->input('anl_ano');
            if (! $ano) {
                return;
            }

            foreach (['anl_dt_inicio_ano', 'anl_dt_fim', 'anl_dt_corte', 'anl_dt_censo'] as $field) {
                $val = $this->input($field);
                if (! $val) {
                    continue;
                }
                $year = (int) date('Y', strtotime($val));
                if ($year !== $ano) {
                    $v->errors()->add($field, "O campo :attribute deve estar dentro do ano {$ano}.");
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'anl_ano' => 'ano',
            'anl_dt_inicio_ano' => 'data de início do ano',
            'anl_dt_fim' => 'fim do ano',
            'anl_dt_corte' => 'data de corte',
            'anl_dt_censo' => 'data do censo',
            'anl_fl_em_exercicio' => 'ano em exercício',
            'anl_fl_progressao_parcial' => 'possui progressão parcial',
            'anl_fl_aprovacao_conselho_freq' => 'aprovação por conselho por frequência',
            'anl_frequencia_minima' => 'frequência mínima',
            'anl_media_geral' => 'média geral',
        ];
    }

    public function messages(): array
    {
        return [
            'anl_dt_fim.after' => 'O :attribute deve ser posterior à data de início do ano.',
            'anl_ano.unique' => 'Já existe um ano letivo cadastrado com este ano.',
        ];
    }
}
