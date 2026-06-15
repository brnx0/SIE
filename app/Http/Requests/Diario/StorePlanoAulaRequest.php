<?php

namespace App\Http\Requests\Diario;

use App\Models\Diario\DiarioPlanoAula;
use App\Models\Turma\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlanoAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->hasRole('professor');
    }

    public function rules(): array
    {
        return [
            'dpa_esc_id'              => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dpa_anl_id'              => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dpa_tur_id'              => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dpa_dis_id'              => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dpa_uni_id'              => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dpa_tema'                => ['required', 'string', 'max:255'],
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
            $funId = (int) $this->user()->fun_id;
            $turId = (int) $this->input('dpa_tur_id');
            $disId = (int) $this->input('dpa_dis_id');
            $dtIni = $this->input('dpa_dt_inicio');
            $dtFim = $this->input('dpa_dt_fim');

            $turma = Turma::find($turId);
            if (! $turma || $turma->tur_modalidade !== Turma::MODALIDADE_REGULAR) {
                $v->errors()->add('dpa_tur_id', 'Apenas turmas regulares são permitidas.');
                return;
            }

            $regente = \DB::table('edu_turma_professor')
                ->where('tup_tur_id', $turId)
                ->where('tup_fun_id', $funId)
                ->where('tup_dis_id', $disId)
                ->whereNull('tup_deleted_at')
                ->exists();

            if (! $regente) {
                $v->errors()->add('dpa_tur_id', 'Você não é regente desta disciplina nesta turma.');
            }

            $planoId = $this->route('plano')?->dpa_id;
            $duplicado = DiarioPlanoAula::where('dpa_fun_id', $funId)
                ->where('dpa_tur_id', $turId)
                ->where('dpa_dis_id', $disId)
                ->where('dpa_dt_inicio', $dtIni)
                ->where('dpa_dt_fim', $dtFim)
                ->when($planoId, fn ($q) => $q->where('dpa_id', '!=', $planoId))
                ->exists();

            if ($duplicado) {
                $v->errors()->add('dpa_dt_inicio', 'Já existe plano para esta turma/disciplina no mesmo período.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'dpa_esc_id'              => 'escola',
            'dpa_anl_id'              => 'ano letivo',
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
