<?php

namespace App\Http\Requests\Matricula;

use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;

        if ($this->has('aluno')) {
            $aluno = $this->input('aluno', []);

            $params = ParametroEntidade::current();
            $nome = $aluno['aln_nome'] ?? '';
            if ($params->par_fl_nome_pessoa_caixa_alta && $nome) {
                $aluno['aln_nome'] = mb_strtoupper($nome, 'UTF-8');
            }

            if (!empty($aluno['aln_cpf'])) $aluno['aln_cpf'] = $strip($aluno['aln_cpf']);
            if (!empty($aluno['aln_cep'])) $aluno['aln_cep'] = $strip($aluno['aln_cep']);
            if (!empty($aluno['aln_nis'])) $aluno['aln_nis'] = $strip($aluno['aln_nis']);

            $this->merge(['aluno' => $aluno]);
        }
    }

    public function rules(): array
    {
        $novoAluno = !$this->filled('mat_aln_id');
        $possuiDeficiencia = $this->boolean('possui_deficiencia');
        $params = ParametroEntidade::current();
        $cpfRule = $params->par_fl_cpf_obrigatorio ? 'required' : 'nullable';

        $rules = [
            // Matrícula
            'mat_tur_id'                   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'mat_aln_id'                   => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
            'mat_tipo_admissao'            => ['nullable'],
            'mat_dt_matricula'             => ['required', 'date'],
            'mat_obs'                      => ['nullable', 'string'],
            'mat_fl_trouxe_transferencia'  => ['boolean'],
            'mat_fl_trouxe_rg'             => ['boolean'],
            'mat_fl_trouxe_reg_nascimento' => ['boolean'],
            'mat_fl_bolsa_familia'         => ['boolean'],
            'mat_fl_recebe_merenda'        => ['boolean'],
            'mat_fl_usa_transporte'        => ['boolean'],
            'mat_fl_usa_biblioteca'        => ['boolean'],
            'possui_deficiencia'           => ['boolean'],
        ];

        if ($novoAluno) {
            $rules += [
                'aluno.aln_nome'          => ['required', 'string', 'max:100'],
                'aluno.aln_dt_nascimento' => ['required', 'date', 'before:today'],
                'aluno.aln_sexo'          => ['required', Rule::in(['M', 'F'])],
                'aluno.aln_cor_raca'      => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
                'aluno.aln_pais_origem'   => ['required', 'string', 'max:60'],
                'aluno.aln_mun_id_nasc'   => ['required', 'integer', 'exists:edu_municipio,mun_id'],
                'aluno.aln_filiacao_1'      => ['required', 'string', 'max:100'],
                'aluno.aln_filiacao_1_tipo' => ['required', Rule::in(['PAI', 'MAE'])],
                'aluno.aln_filiacao_2'      => ['nullable', 'string', 'max:100'],
                'aluno.aln_filiacao_2_tipo' => ['nullable', Rule::in(['PAI', 'MAE'])],
                'aluno.aln_cpf'           => [$cpfRule, 'nullable', 'digits:11', new Cpf,
                    Rule::unique('edu_aluno', 'aln_cpf')->whereNull('aln_deleted_at')],
                'aluno.aln_nr_certidao'   => ['nullable', 'string', 'max:32'],
                'aluno.aln_nis'           => ['nullable', 'digits:11'],
                'aluno.aln_cep'           => ['nullable', 'digits:8'],
                'aluno.aln_logradouro'    => ['nullable', 'string', 'max:150'],
                'aluno.aln_numero'        => ['nullable', 'string', 'max:10'],
                'aluno.aln_complemento'   => ['nullable', 'string', 'max:100'],
                'aluno.aln_bairro'        => ['nullable', 'string', 'max:100'],
                'aluno.aln_cidade'        => ['nullable', 'string', 'max:100'],
                'aluno.aln_uf'            => ['nullable', 'string', 'size:2'],
                'aluno.aln_telefone'      => ['nullable', 'digits_between:10,11'],
                'aluno.aln_email'         => ['nullable', 'email', 'max:150'],
            ];
        }

        // Saúde sempre aceita (PCD ou não), só deficiências obrigatórias quando PCD
        $rules += [
            'saude'                           => ['array'],
            'saude.als_tipo_sanguineo'        => ['nullable', Rule::in(['A+','A-','B+','B-','AB+','AB-','O+','O-'])],
            'saude.als_ds_alergias'           => ['nullable', 'string'],
            'saude.als_contato_emergencia'    => ['nullable', 'string', 'max:150'],
            'saude.als_telefone_emergencia'   => ['nullable', 'string', 'max:20'],
            'saude.als_plano_saude'           => ['nullable', 'string', 'max:100'],
            'saude.als_cartao_sus'            => ['nullable', 'string', 'max:20'],
            'saude.als_alergia_a'             => ['nullable', 'string', 'max:500'],
            'saude.als_remedio_febre'         => ['nullable', 'string', 'max:200'],
            'saude.als_remedio_cefaleia'      => ['nullable', 'string', 'max:200'],
            'saude.als_patologias'            => ['nullable', 'array'],
            'saude.als_outra_doenca'          => ['nullable', 'string', 'max:500'],
            'saude.als_patologias_infancia'   => ['nullable', 'array'],
            'saude.als_outra_doenca_infancia' => ['nullable', 'string', 'max:500'],
            'saude.als_deficiencias'          => ['nullable', 'array'],
            'saude.als_deficiencias.*'        => ['string'],
            'saude.als_transtornos_globais'   => ['nullable', 'array'],
            'saude.als_transtornos_aprendizagem' => ['nullable', 'array'],
            'saude.als_deficiencia_outro'     => ['nullable', 'string', 'max:500'],
            'saude.als_fl_altas_habilidades'  => ['boolean'],
            'saude.als_cid'                   => ['nullable', 'string', 'max:20'],
            'saude.als_observacao'            => ['nullable', 'string', 'max:2000'],
            'saude.als_clinicas'              => ['nullable', 'array'],
            'saude.als_recursos_inep'         => ['nullable', 'array'],
        ];

        if ($possuiDeficiencia) {
            $rules['saude.als_deficiencias'] = ['required', 'array', 'min:1'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'mat_tur_id'          => 'turma',
            'mat_tipo_admissao'   => 'tipo de admissão',
            'mat_dt_matricula'    => 'data de matrícula',
            'aluno.aln_nome'      => 'nome',
            'aluno.aln_dt_nascimento' => 'data de nascimento',
            'aluno.aln_sexo'      => 'sexo',
            'aluno.aln_cor_raca'  => 'cor/raça',
            'aluno.aln_filiacao_1'      => 'filiação 1',
            'aluno.aln_filiacao_1_tipo' => 'tipo da filiação 1',
            'aluno.aln_filiacao_2_tipo' => 'tipo da filiação 2',
            'aluno.aln_mun_id_nasc' => 'município de nascimento',
            'aluno.aln_cpf'       => 'CPF',
            'saude.als_deficiencias' => 'deficiências',
        ];
    }

    public function messages(): array
    {
        return [
            'saude.als_deficiencias.required' => 'Informe ao menos uma deficiência.',
            'saude.als_deficiencias.min'       => 'Informe ao menos uma deficiência.',
        ];
    }
}
