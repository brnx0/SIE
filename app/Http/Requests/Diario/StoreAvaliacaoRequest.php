<?php

namespace App\Http\Requests\Diario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvaliacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->hasRole('professor'));
    }

    public function rules(): array
    {
        return [
            'ava_esc_id'         => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'ava_anl_id'         => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'ava_tur_id'         => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'ava_dis_id'         => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'ava_uni_id'         => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'ava_iav_id'         => ['required', 'integer', 'exists:edu_instrumento_avaliativo,iav_id'],
            'ava_tipo'           => ['required', 'in:numerica,conceitual'],
            'ava_descricao'      => ['nullable', 'string', 'max:150'],
            'ava_dt'             => ['required', 'date'],
            'ava_valor'          => ['required', 'numeric', 'min:0.01', 'max:10'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ava_iav_id'    => 'instrumento',
            'ava_descricao' => 'descrição',
            'ava_dt'        => 'data',
            'ava_valor'     => 'valor',
        ];
    }
}
