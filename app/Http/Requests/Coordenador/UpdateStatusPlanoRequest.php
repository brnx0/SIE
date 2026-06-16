<?php

namespace App\Http\Requests\Coordenador;

use App\Models\Diario\DiarioPlanoAula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateStatusPlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->hasRole('coordenador');
    }

    public function rules(): array
    {
        return [
            'dpa_status'          => ['required', 'in:aprovado,reprovado,correcao'],
            'dpa_obs_coordenador' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $plano = $this->route('plano');
            if (! $plano instanceof DiarioPlanoAula) {
                return;
            }

            if ($plano->dpa_status !== DiarioPlanoAula::STATUS_PENDENTE) {
                $v->errors()->add('dpa_status', 'Apenas planos pendentes podem ser revisados.');
                return;
            }

            $user = $this->user();

            if ($user->isAdmin()) {
                return;
            }

            if ((int) $plano->dpa_user_id === (int) $user->id) {
                $v->errors()->add('dpa_status', 'Você não pode validar um plano que você mesmo criou.');
                return;
            }

            $funId = (int) $user->fun_id;

            if (! $funId) {
                $v->errors()->add('dpa_status', 'Seu usuário não possui vínculo de funcionário para verificar lotação.');
                return;
            }

            $escId = (int) DB::table('edu_turma')->where('tur_id', $plano->dpa_tur_id)->value('tur_esc_id');

            $temLotacao = DB::table('edu_funcionario_lotacao as l')
                ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
                ->where('a.adm_fun_id', $funId)
                ->whereNull('a.adm_deleted_at')
                ->where('l.lot_esc_id', $escId)
                ->where('l.lot_fl_ativo', true)
                ->exists();

            if (! $temLotacao) {
                $v->errors()->add('dpa_status', 'Você não tem lotação ativa na escola desta turma.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'dpa_status'          => 'status',
            'dpa_obs_coordenador' => 'observação do coordenador',
        ];
    }
}
