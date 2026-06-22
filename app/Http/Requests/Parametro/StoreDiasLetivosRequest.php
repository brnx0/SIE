<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiasLetivosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'dlt_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dlt_seg_id'    => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'meses'         => ['array'],
            'meses.*'       => ['nullable', 'integer', 'min:0', 'max:31'],
            'periodos'      => ['array'],
            'periodos.*'    => ['nullable', 'integer', 'min:0', 'max:366'],
        ];
    }

    public function attributes(): array
    {
        return [
            'dlt_anl_id' => 'ano letivo',
            'dlt_seg_id' => 'segmento',
        ];
    }
}
