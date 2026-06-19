<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\MotivoBaixaFrequencia;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreMotivoBaixaFrequenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['secretaria_escola', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'mbf_descricao' => ['required', 'string', 'min:3', 'max:255'],
            'mbf_fl_abona'  => ['boolean'],
            'mbf_fl_ativo'  => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $id = $this->route('motivo')?->mbf_id;

            $descricao = trim((string) $this->input('mbf_descricao'));
            if ($descricao !== '') {
                $existe = MotivoBaixaFrequencia::query()
                    ->whereRaw('UPPER(mbf_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
                    ->when($id, fn ($q) => $q->where('mbf_id', '!=', $id))
                    ->exists();
                if ($existe) {
                    $v->errors()->add('mbf_descricao', 'Já existe um motivo com esta descrição.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return ['mbf_descricao' => 'descrição', 'mbf_fl_ativo' => 'ativo'];
    }
}
