<?php

namespace App\Http\Requests\Aluno;

use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlunoRequest extends FormRequest
{
    protected ?ParametroEntidade $parametros = null;

    protected function parametros(): ParametroEntidade
    {
        return $this->parametros ??= ParametroEntidade::current();
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;
        $params = $this->parametros();

        $nome = $this->input('aln_nome');
        if ($params->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $nomeSocial = $this->input('aln_nome_social');
        if ($params->par_fl_nome_pessoa_caixa_alta && is_string($nomeSocial) && $nomeSocial !== '') {
            $nomeSocial = mb_strtoupper($nomeSocial, 'UTF-8');
        }

        $this->merge([
            'aln_nome'        => $nome,
            'aln_nome_social' => $this->filled('aln_nome_social') ? $nomeSocial : null,
            'aln_cpf' => $this->filled('aln_cpf') ? $strip($this->input('aln_cpf')) : null,
            'aln_cep' => $this->filled('aln_cep') ? $strip($this->input('aln_cep')) : null,
            'aln_telefone' => $this->filled('aln_telefone') ? $strip($this->input('aln_telefone')) : null,
            'aln_cd_inep' => $this->filled('aln_cd_inep') ? $strip($this->input('aln_cd_inep')) : null,
            'aln_uf' => $this->filled('aln_uf') ? strtoupper($this->input('aln_uf')) : null,
        ]);
    }

    public function rules(): array
    {
        $alunoId = $this->route('aluno')?->aln_id;
        $params = $this->parametros();
        $cpfRule = $params->par_fl_cpf_obrigatorio ? 'required' : 'nullable';

        return [
            'aln_nome'        => ['required', 'string', 'max:100'],
            'aln_nome_social' => ['nullable', 'string', 'max:100'],
            'aln_dt_nascimento' => ['required', 'date', 'before:today'],
            'aln_sexo' => ['required', Rule::in(['M', 'F'])],
            'aln_cor_raca' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
            'aln_pais_origem' => ['required', 'string', 'max:60'],
            'aln_mun_id_nasc' => ['required', 'integer', 'exists:edu_municipio,mun_id'],

            'aln_cpf' => [$cpfRule, 'nullable', 'digits:11', new Cpf, Rule::unique('edu_aluno', 'aln_cpf')->ignore($alunoId, 'aln_id')->whereNull('aln_deleted_at')],
            'aln_cd_inep' => ['nullable', 'digits:12', Rule::unique('edu_aluno', 'aln_cd_inep')->ignore($alunoId, 'aln_id')->whereNull('aln_deleted_at')],
            'aln_nr_matricula' => ['nullable', 'integer', 'min:1', Rule::unique('edu_aluno', 'aln_nr_matricula')->ignore($alunoId, 'aln_id')->whereNull('aln_deleted_at')],
            'aln_nr_certidao' => ['nullable', 'string', 'max:32'],

            'aln_filiacao_1' => ['required', 'string', 'max:100'],
            'aln_filiacao_2' => ['nullable', 'string', 'max:100'],

            'aln_cep' => ['nullable', 'digits:8'],
            'aln_logradouro' => ['nullable', 'string', 'max:150'],
            'aln_numero' => ['nullable', 'string', 'max:10'],
            'aln_complemento' => ['nullable', 'string', 'max:100'],
            'aln_bairro' => ['nullable', 'string', 'max:100'],
            'aln_cidade' => ['nullable', 'string', 'max:100'],
            'aln_uf' => ['nullable', 'string', 'size:2'],

            'aln_telefone' => ['nullable', 'digits_between:10,11'],
            'aln_email' => ['nullable', 'email', 'max:150'],

            'aln_foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'aln_fl_ativo' => ['boolean'],

            'saude' => ['array'],
            'saude.als_tipo_sanguineo' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'saude.als_ds_alergias' => ['nullable', 'string'],
            'saude.als_fl_pcd' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->parametros()->par_fl_alertar_acentos_nomes) {
                return;
            }
            $nome = (string) $this->input('aln_nome', '');
            // Detecta caracteres acentuados (qualquer marca diacrítica).
            if ($nome !== '' && preg_match('/[\x{0300}-\x{036f}]|[À-ÖØ-öø-ÿ]/u', $nome)) {
                $v->errors()->add('aln_nome', 'O nome contém acentos. Conforme parâmetros do sistema, cadastre nomes sem acentuação.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'aln_nome'        => 'nome completo',
            'aln_nome_social' => 'nome social',
            'aln_dt_nascimento' => 'data de nascimento',
            'aln_sexo' => 'sexo',
            'aln_cor_raca' => 'cor / raça',
            'aln_pais_origem' => 'país de origem',
            'aln_mun_id_nasc' => 'município de nascimento',
            'aln_cpf' => 'CPF',
            'aln_cd_inep' => 'identificação INEP',
            'aln_nr_matricula' => 'número de matrícula',
            'aln_nr_certidao' => 'matrícula da certidão',
            'aln_filiacao_1' => 'filiação 1',
            'aln_filiacao_2' => 'filiação 2',
            'aln_cep' => 'CEP',
            'aln_logradouro' => 'logradouro',
            'aln_numero' => 'número',
            'aln_complemento' => 'complemento',
            'aln_bairro' => 'bairro',
            'aln_cidade' => 'cidade',
            'aln_uf' => 'UF',
            'aln_telefone' => 'telefone',
            'aln_email' => 'e-mail',
            'aln_foto' => 'foto do aluno',
            'saude.als_tipo_sanguineo' => 'tipo sanguíneo',
            'saude.als_ds_alergias' => 'alergias / restrições',
            'saude.als_fl_pcd' => 'aluno PCD / TGD / AH',
        ];
    }
}
