<?php

namespace App\Http\Requests\Transporte;

use App\Models\Transporte\Rota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rot_nome'     => is_string($this->input('rot_nome')) ? trim($this->input('rot_nome')) : $this->input('rot_nome'),
            'rot_tmo_id'   => $this->input('rot_tmo_id') ?: null,
            'rot_zona'     => $this->input('rot_zona') ?: null,
            'rot_fl_ativo' => $this->boolean('rot_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'rot_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'rot_nome'   => [
                'required', 'string', 'max:120',
                Rule::unique('edu_transporte_rota', 'rot_nome')
                    ->where(fn ($q) => $q->where('rot_anl_id', $this->input('rot_anl_id')))
                    ->whereNull('rot_deleted_at')
                    ->ignore($this->route('rota')?->rot_id, 'rot_id'),
            ],
            'rot_turno'  => ['required', Rule::in(array_keys(Rota::TURNOS))],
            'rot_zona'   => ['nullable', Rule::in(array_keys(Rota::ZONAS))],
            'rot_vei_id' => ['required', 'integer', 'exists:edu_transporte_veiculo,vei_id'],
            'rot_mot_id' => ['required', 'integer', 'exists:edu_transporte_motorista,mot_id'],
            'rot_tmo_id' => ['nullable', 'integer', 'exists:edu_transporte_monitor,tmo_id'],
            'rot_obs'    => ['nullable', 'string', 'max:2000'],
            'rot_fl_ativo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rot_nome.unique' => 'Já existe uma rota com este nome neste ano letivo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rot_anl_id' => 'ano letivo',
            'rot_nome'   => 'nome da rota',
            'rot_turno'  => 'turno',
            'rot_zona'   => 'zona',
            'rot_vei_id' => 'veículo',
            'rot_mot_id' => 'motorista',
            'rot_tmo_id' => 'monitor',
            'rot_obs'    => 'observações',
            'rot_fl_ativo' => 'situação',
        ];
    }
}
