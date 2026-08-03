<?php

namespace App\Http\Requests\Diario;

use App\Http\Requests\Concerns\ValidaPeriodoDaUnidade;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Turma\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlanoAulaRequest extends FormRequest
{
    use ValidaPeriodoDaUnidade;

    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        // Gestor global (admin/gestor_secretaria) é equiparado ao administrador:
        // cria/edita planos sem exigir vínculo de regência. O acesso à tela em si
        // já é gateado pela matriz de permissão (middleware `tela`).
        if ($user->isGestorGlobal()) return true;
        if (!$user->hasRole('professor')) return false;

        // Edição: apenas o autor do próprio plano
        $plano = $this->route('plano');
        return !$plano || (int) $plano->dpa_user_id === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'dpa_esc_id'              => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dpa_anl_id'              => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dpa_ser_id'              => ['required', 'integer', 'exists:edu_serie,ser_id'],
            // Opcional: só aceito em série com turma multi (ver withValidator).
            'dpa_tur_id'              => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'dpa_dis_id'              => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dpa_uni_id'              => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dpa_tema'                => ['required', 'string', 'max:50'],
            'dpa_dt_inicio'           => ['required', 'date'],
            'dpa_dt_fim'              => ['required', 'date', 'after_or_equal:dpa_dt_inicio'],
            'dpa_objeto_conhecimento' => ['required', 'string', 'max:5000'],
            'dpa_estrategias'         => ['nullable', 'string', 'max:10000'],
            'dpa_recursos'            => ['required', 'string', 'max:5000'],
            'dpa_competencias'        => ['nullable', 'string', 'max:5000'],
            'dpa_avaliacao'           => ['nullable', 'string', 'max:5000'],
            'dpa_objetivos_complementares' => ['nullable', 'string', 'max:5000'],
            'indicadores'             => ['nullable', 'array'],
            'indicadores.*'           => ['integer', 'exists:edu_serie_indicador,ind_id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $user = $this->user();
            $funId = (int) $user->fun_id;
            $serId = (int) $this->input('dpa_ser_id');
            $disId = (int) $this->input('dpa_dis_id');
            $escId = (int) $this->input('dpa_esc_id');
            $anlId = (int) $this->input('dpa_anl_id');
            $dtIni = $this->input('dpa_dt_inicio');
            $dtFim = $this->input('dpa_dt_fim');

            // Antes dos returns abaixo: a data fora do período independe dos demais vínculos.
            $this->validarDatasNaUnidade($v, (int) $this->input('dpa_uni_id'), $dtIni, $dtFim, 'dpa_dt_inicio', 'dpa_dt_fim');

            // Período já encerrado não recebe plano NOVO (na edição, o período do
            // próprio plano continua valendo).
            if (! $this->route('plano')) {
                $this->validarUnidadeNaoEncerrada($v, (int) $this->input('dpa_uni_id'));
            }

            // A série precisa ter turma REGULAR nesta escola/ano (o plano é por série vinculada às turmas).
            $temTurmaRegular = \DB::table('edu_turma')
                ->where('tur_ser_id', $serId)
                ->where('tur_esc_id', $escId)
                ->where('tur_anl_id', $anlId)
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                ->whereNull('tur_deleted_at')
                ->exists();
            if (! $temTurmaRegular) {
                $v->errors()->add('dpa_ser_id', 'Série sem turma regular nesta escola/ano.');
                return;
            }

            // Turma é OPCIONAL e só vale em série multi (ser_fl_multi). Quando
            // informada, precisa ser turma regular da série/escola/ano.
            $turId = (int) $this->input('dpa_tur_id');
            if ($turId) {
                $serieMulti = (bool) \DB::table('edu_serie')->where('ser_id', $serId)->value('ser_fl_multi');
                if (! $serieMulti) {
                    $v->errors()->add('dpa_tur_id', 'Turma só pode ser informada em série com turma multi.');
                } elseif (! \DB::table('edu_turma')
                    ->where('tur_id', $turId)
                    ->where('tur_ser_id', $serId)
                    ->where('tur_esc_id', $escId)
                    ->where('tur_anl_id', $anlId)
                    ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                    ->whereNull('tur_deleted_at')
                    ->exists()
                ) {
                    $v->errors()->add('dpa_tur_id', 'Turma não pertence à série/escola/ano informados.');
                }
            }

            // Gestor global (admin/gestor_secretaria) cria para qualquer série/disciplina
            // vinculada à escola/ano, sem precisar ser o professor regente.
            if (! $user->isGestorGlobal()) {
                if (! $funId) {
                    $v->errors()->add('dpa_ser_id', 'Seu usuário não possui vínculo de funcionário.');
                    return;
                }

                $regente = \DB::table('edu_turma_professor as tp')
                    ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                    ->where('tp.tup_fun_id', $funId)
                    ->where('tp.tup_dis_id', $disId)
                    ->where('t.tur_ser_id', $serId)
                    ->where('t.tur_esc_id', $escId)
                    ->where('t.tur_anl_id', $anlId)
                    ->where('t.tur_modalidade', Turma::MODALIDADE_REGULAR)
                    ->whereNull('tp.tup_deleted_at')
                    ->whereNull('t.tur_deleted_at')
                    ->exists();

                if (! $regente) {
                    $v->errors()->add('dpa_ser_id', 'Você não leciona esta disciplina em turmas desta série.');
                }
            }

            $planoModel = $this->route('plano');
            $planoId = $planoModel?->dpa_id;
            // No cadastro: autor é o usuário logado. Na edição: mantém o autor original do plano.
            $checkUserId = $planoModel ? (int) $planoModel->dpa_user_id : (int) $user->id;
            $duplicado = DiarioPlanoAula::where('dpa_user_id', $checkUserId)
                ->where('dpa_ser_id', $serId)
                ->where('dpa_dis_id', $disId)
                ->where('dpa_dt_inicio', $dtIni)
                ->where('dpa_dt_fim', $dtFim)
                ->when($planoId, fn ($q) => $q->where('dpa_id', '!=', $planoId))
                ->exists();

            if ($duplicado) {
                $v->errors()->add('dpa_dt_inicio', 'Já existe plano para esta série/disciplina no mesmo período.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'dpa_esc_id'              => 'escola',
            'dpa_anl_id'              => 'ano letivo',
            'dpa_ser_id'              => 'série',
            'dpa_tur_id'              => 'turma',
            'dpa_dis_id'              => 'componente curricular',
            'dpa_uni_id'              => 'unidade',
            'dpa_tema'                => 'tema',
            'dpa_dt_inicio'           => 'data inicial',
            'dpa_dt_fim'              => 'data final',
            'dpa_objeto_conhecimento' => 'objeto do conhecimento',
            'dpa_recursos'            => 'recursos didáticos',
        ];
    }
}
