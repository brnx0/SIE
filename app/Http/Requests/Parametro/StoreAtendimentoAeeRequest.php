<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\AtendimentoAee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAtendimentoAeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'ate_descricao' => ['required', 'string', 'min:3', 'max:200'],
            'ate_fl_ativo'  => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $descricao = trim((string) $this->input('ate_descricao'));
            if ($descricao === '') {
                return;
            }

            $id = $this->route('atendimentoAee')?->ate_id;

            $existe = AtendimentoAee::query()
                ->whereRaw('UPPER(ate_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
                ->when($id, fn ($q) => $q->where('ate_id', '!=', $id))
                ->exists();

            if ($existe) {
                $v->errors()->add('ate_descricao', 'Já existe um atendimento com esta descrição.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'ate_descricao' => 'descrição',
            'ate_fl_ativo'  => 'ativo',
        ];
    }
}
