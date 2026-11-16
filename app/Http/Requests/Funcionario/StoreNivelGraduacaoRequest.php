<?php

namespace App\Http\Requests\Funcionario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\DB;

class StoreNivelGraduacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ngr_nome'     => trim((string) $this->input('ngr_nome', '')),
            'ngr_fl_ativo' => $this->boolean('ngr_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'ngr_nome'     => ['required', 'string', 'max:60'],
            'ngr_fl_ativo' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->has('ngr_nome')) {
                return;
            }

            $existe = DB::table('edu_nivel_graduacao')
                ->whereRaw('UPPER(ngr_nome) = UPPER(?)', [$this->input('ngr_nome')])
                ->when($this->route('nivel')?->ngr_id, fn ($q, $id) => $q->where('ngr_id', '<>', $id))
                ->exists();

            if ($existe) {
                $v->errors()->add('ngr_nome', 'Já existe um nível de graduação com este nome.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'ngr_nome'     => 'nome',
            'ngr_fl_ativo' => 'situação',
        ];
    }
}
