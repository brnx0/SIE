<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaEscolaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $selfId = $this->route('mediaEscola')?->mde_id;

        return [
            'mde_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'mde_esc_id' => [
                'required', 'integer', 'exists:edu_escola,esc_id',
                Rule::unique('cfg_media_escola', 'mde_esc_id')
                    ->where('mde_anl_id', $this->input('mde_anl_id'))
                    ->ignore($selfId, 'mde_id'),
            ],
            'mde_media'  => ['required', 'numeric', 'between:0,10'],
            'mde_cnc_id' => ['nullable', 'integer', 'exists:cfg_conceito,cnc_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mde_esc_id.unique' => 'Esta escola já possui média específica cadastrada neste ano letivo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'mde_anl_id' => 'ano letivo',
            'mde_esc_id' => 'escola',
            'mde_media'  => 'média',
            'mde_cnc_id' => 'média conceitual',
        ];
    }
}
