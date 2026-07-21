<?php

namespace App\Http\Requests\Transporte;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiarioBordoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tdb_tmo_id'     => $this->input('tdb_tmo_id') ?: null,
            'tdb_hr_saida'   => $this->input('tdb_hr_saida') ?: null,
            'tdb_hr_chegada' => $this->input('tdb_hr_chegada') ?: null,
            'tdb_km_inicial' => $this->filled('tdb_km_inicial') ? $this->input('tdb_km_inicial') : null,
            'tdb_km_final'   => $this->filled('tdb_km_final') ? $this->input('tdb_km_final') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'tdb_vei_id'     => ['required', 'integer', 'exists:edu_transporte_veiculo,vei_id'],
            'tdb_mot_id'     => ['required', 'integer', 'exists:edu_transporte_motorista,mot_id'],
            'tdb_tmo_id'     => ['nullable', 'integer', 'exists:edu_transporte_monitor,tmo_id'],
            'tdb_hr_saida'   => ['nullable', 'date_format:H:i'],
            'tdb_hr_chegada' => ['nullable', 'date_format:H:i', 'after_or_equal:tdb_hr_saida'],
            'tdb_km_inicial' => ['nullable', 'numeric', 'min:0'],
            'tdb_km_final'   => ['nullable', 'numeric', 'min:0', 'gte:tdb_km_inicial'],
            'tdb_obs'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tdb_hr_chegada.after_or_equal' => 'A hora de chegada deve ser igual ou posterior à saída.',
            'tdb_km_final.gte'              => 'O km final deve ser maior ou igual ao km inicial.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tdb_vei_id'     => 'veículo',
            'tdb_mot_id'     => 'motorista',
            'tdb_tmo_id'     => 'monitor',
            'tdb_hr_saida'   => 'hora de saída',
            'tdb_hr_chegada' => 'hora de chegada',
            'tdb_km_inicial' => 'km inicial',
            'tdb_km_final'   => 'km final',
            'tdb_obs'        => 'observações',
        ];
    }
}
