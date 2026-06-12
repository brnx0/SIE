<?php

namespace App\Http\Requests\Coordenador;

use App\Models\Diario\DiarioPlanoAee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateStatusPlanoAeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;
        return in_array($role, ['coordenador_interno', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'dae_status'          => ['required', 'in:aprovado,reprovado,correcao'],
            'dae_obs_coordenador' => ['required', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $plano = $this->route('plano');
            if (! $plano instanceof DiarioPlanoAee) {
                return;
            }

            if ($plano->dae_status !== DiarioPlanoAee::STATUS_PENDENTE) {
                $v->errors()->add('dae_status', 'Apenas planos pendentes podem ser revisados.');
                return;
            }

            $funId = (int) ($this->user()->fun_id ?? 0);

            if ($funId && (int) $plano->dae_fun_id === $funId) {
                $v->errors()->add('dae_status', 'Você não pode validar um plano que você mesmo criou.');
                return;
            }
        });
    }

    public function attributes(): array
    {
        return [
            'dae_status'          => 'status',
            'dae_obs_coordenador' => 'observação do coordenador',
        ];
    }
}
