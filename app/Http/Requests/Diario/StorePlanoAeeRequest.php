<?php

namespace App\Http\Requests\Diario;

use App\Models\Diario\DiarioPlanoAee;
use App\Models\Turma\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StorePlanoAeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->hasAnyRole(['professor', 'professor_aee']);
    }

    public function rules(): array
    {
        return [
            'dae_esc_id'      => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dae_anl_id'      => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dae_tur_id'      => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dae_tema'        => ['required', 'string', 'max:255'],
            'dae_dt_inicio'   => ['required', 'date'],
            'dae_dt_fim'      => ['required', 'date', 'after_or_equal:dae_dt_inicio'],
            'dae_objetivo'    => ['required', 'string', 'max:10000'],
            'dae_diagnostico' => ['nullable', 'string', 'max:5000'],
            'dae_area_desenv' => ['nullable', 'string', 'max:5000'],
            'dae_metas'       => ['required', 'string', 'max:10000'],
            'dae_estrategias' => ['required', 'string', 'max:10000'],
            'dae_recursos'    => ['required', 'string', 'max:5000'],
            'dae_avaliacao'   => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $user = $this->user();
            $funId = (int) $user->fun_id;
            $turId = (int) $this->input('dae_tur_id');
            $dtIni = $this->input('dae_dt_inicio');
            $dtFim = $this->input('dae_dt_fim');

            $turma = Turma::find($turId);
            if (! $turma || $turma->tur_modalidade !== Turma::MODALIDADE_AEE) {
                $v->errors()->add('dae_tur_id', 'Apenas turmas AEE são permitidas.');
                return;
            }

            if (! $user->isAdmin()) {
                if (! $funId) {
                    $v->errors()->add('dae_tur_id', 'Seu usuário não possui vínculo de funcionário.');
                    return;
                }

                $temLotacao = DB::table('edu_funcionario_lotacao as l')
                    ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
                    ->where('a.adm_fun_id', $funId)
                    ->whereNull('a.adm_deleted_at')
                    ->where('l.lot_esc_id', $turma->tur_esc_id)
                    ->where('l.lot_fl_ativo', true)
                    ->whereJsonContains('l.lot_funcoes_sala_aula', 'Docente AEE')
                    ->exists();

                if (! $temLotacao) {
                    $v->errors()->add('dae_tur_id', 'Você precisa ter lotação ativa como "Docente AEE" na escola desta turma.');
                }
            }

            $planoModel = $this->route('plano');
            $planoId = $planoModel?->dae_id;
            // No cadastro: autor é o usuário logado. Na edição: mantém o autor original do plano.
            $checkUserId = $planoModel ? (int) $planoModel->dae_user_id : (int) $user->id;
            $duplicado = DiarioPlanoAee::where('dae_user_id', $checkUserId)
                ->where('dae_tur_id', $turId)
                ->where('dae_dt_inicio', $dtIni)
                ->where('dae_dt_fim', $dtFim)
                ->when($planoId, fn ($q) => $q->where('dae_id', '!=', $planoId))
                ->exists();

            if ($duplicado) {
                $v->errors()->add('dae_dt_inicio', 'Já existe plano para esta turma no mesmo período.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'dae_esc_id'      => 'escola',
            'dae_anl_id'      => 'ano letivo',
            'dae_tur_id'      => 'turma',
            'dae_tema'        => 'tema',
            'dae_dt_inicio'   => 'data inicial',
            'dae_dt_fim'      => 'data final',
            'dae_objetivo'    => 'objetivo das intervenções',
            'dae_metas'       => 'metas de aprendizagem',
            'dae_estrategias' => 'estratégias de intervenção',
            'dae_recursos'    => 'recursos utilizados',
        ];
    }
}
