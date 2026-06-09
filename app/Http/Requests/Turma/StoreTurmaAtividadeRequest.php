<?php

namespace App\Http\Requests\Turma;

use App\Models\Turma\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTurmaAtividadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tur_esc_id'             => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_anl_id'             => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_cd_inep'            => ['nullable', 'string', 'max:20'],
            'tur_nome'               => ['required', 'string', 'max:20'],
            'tur_turno'              => ['required', Rule::in(Turma::TURNOS)],
            'tur_capacidade'         => ['required', 'integer', 'min:1', 'max:999'],
            'tur_semestre'           => ['required', 'integer', Rule::in(Turma::SEMESTRES)],
            'tur_situacao'           => ['required', Rule::in(Turma::SITUACOES)],
            'tur_hora_inicio'        => ['nullable', 'date_format:H:i'],
            'tur_hora_fim'           => ['nullable', 'date_format:H:i', 'after:tur_hora_inicio'],
            'tur_mediacao'           => ['nullable', Rule::in(Turma::TIPOS_MEDIACAO)],
            'tur_aee_sala'           => ['nullable', 'string', 'max:100'],
            'tur_dias_funcionamento' => ['nullable', 'array'],
            'tur_dias_funcionamento.*' => ['string', Rule::in(Turma::DIAS_SEMANA)],
            'tur_obs'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tur_modalidade'       => Turma::MODALIDADE_ATIVIDADE,
            'tur_tipo_atendimento' => 'ATIVIDADE COMPLEMENTAR',
            'tur_seg_id'           => null,
            'tur_ser_id'           => null,
        ]);
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        $data['tur_modalidade']       = Turma::MODALIDADE_ATIVIDADE;
        $data['tur_tipo_atendimento'] = 'ATIVIDADE COMPLEMENTAR';
        $data['tur_seg_id']           = null;
        $data['tur_ser_id']           = null;

        return $data;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $escId = (int) $this->input('tur_esc_id');
            $anlId = (int) $this->input('tur_anl_id');
            $turno = $this->input('tur_turno');
            $nome  = $this->input('tur_nome');
            $turma = $this->route('turmaAtividade');
            $turId = $turma?->tur_id;

            $exists = Turma::atividade()
                ->where('tur_esc_id', $escId)
                ->where('tur_anl_id', $anlId)
                ->where('tur_turno', $turno)
                ->whereRaw('lower(tur_nome) = lower(?)', [$nome])
                ->when($turId, fn ($q) => $q->where('tur_id', '!=', $turId))
                ->exists();

            if ($exists) {
                $v->errors()->add('tur_nome', 'Já existe uma turma de atividade com este nome para a escola, ano letivo e turno informados.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'tur_esc_id'             => 'escola',
            'tur_anl_id'             => 'ano letivo',
            'tur_cd_inep'            => 'código INEP',
            'tur_nome'               => 'nome da turma',
            'tur_turno'              => 'turno',
            'tur_capacidade'         => 'capacidade',
            'tur_semestre'           => 'semestre',
            'tur_situacao'           => 'situação',
            'tur_hora_inicio'        => 'hora início',
            'tur_hora_fim'           => 'hora fim',
            'tur_mediacao'           => 'tipo de mediação',
            'tur_aee_sala'           => 'sala / local',
            'tur_dias_funcionamento' => 'dias de funcionamento',
            'tur_obs'                => 'observação',
        ];
    }
}
