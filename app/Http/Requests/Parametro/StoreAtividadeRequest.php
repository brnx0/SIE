<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\Atividade;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAtividadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'atv_descricao' => ['required', 'string', 'min:3', 'max:200'],
            'atv_fl_ativo'  => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $descricao = trim((string) $this->input('atv_descricao'));
            if ($descricao === '') {
                return;
            }

            $id = $this->route('atividade')?->atv_id;

            $existe = Atividade::query()
                ->whereRaw('UPPER(atv_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
                ->when($id, fn ($q) => $q->where('atv_id', '!=', $id))
                ->exists();

            if ($existe) {
                $v->errors()->add('atv_descricao', 'Já existe uma atividade com esta descrição.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'atv_descricao' => 'descrição',
            'atv_fl_ativo'  => 'ativo',
        ];
    }
}
