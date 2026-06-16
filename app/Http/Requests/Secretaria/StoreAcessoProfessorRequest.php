<?php

namespace App\Http\Requests\Secretaria;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcessoProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->hasRole('secretaria_escola'));
    }

    public function rules(): array
    {
        return [
            'fun_ids'   => ['required', 'array', 'min:1'],
            'fun_ids.*' => ['integer', 'exists:edu_funcionario,fun_id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fun_ids' => 'professores',
        ];
    }
}
