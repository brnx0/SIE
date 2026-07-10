<?php

namespace App\Http\Requests\Funcionario;

use App\Models\Aluno\Aluno;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\ParametroEntidade;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuncionarioRequest extends FormRequest
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

        $nome = $this->input('fun_nome');
        if ($params->par_fl_nome_pessoa_caixa_alta && is_string($nome)) {
            $nome = mb_strtoupper($nome, 'UTF-8');
        }

        $nomeSocial = $this->input('fun_nome_social');
        if ($params->par_fl_nome_pessoa_caixa_alta && is_string($nomeSocial) && $nomeSocial !== '') {
            $nomeSocial = mb_strtoupper($nomeSocial, 'UTF-8');
        }

        // Povo indígena só se aplica quando etnia/cor/raça = Indígena (5).
        // Mantém a string legada fun_povo_indigena (censo/relatórios) sincronizada com o povo escolhido.
        $indigena = (int) $this->input('fun_cor_raca') === 5;
        $pinId    = $indigena ? ($this->input('fun_pin_id') ?: null) : null;
        $povoNome = $pinId ? \App\Models\PovoIndigena::whereKey($pinId)->value('pin_nome') : null;

        $this->merge([
            'fun_pin_id'        => $pinId,
            'fun_povo_indigena' => $povoNome,
            'fun_nome'        => $nome,
            'fun_nome_social' => $this->filled('fun_nome_social') ? $nomeSocial : null,
            'fun_cpf' => $this->filled('fun_cpf') ? $strip($this->input('fun_cpf')) : null,
            'fun_cep' => $this->filled('fun_cep') ? $strip($this->input('fun_cep')) : null,
            'fun_telefone' => $this->filled('fun_telefone') ? $strip($this->input('fun_telefone')) : null,
            'fun_celular' => $this->filled('fun_celular') ? $strip($this->input('fun_celular')) : null,
            'fun_cd_censo' => $this->filled('fun_cd_censo') ? $strip($this->input('fun_cd_censo')) : null,
            'fun_uf' => $this->filled('fun_uf') ? strtoupper($this->input('fun_uf')) : null,
            'fun_rg_uf' => $this->filled('fun_rg_uf') ? strtoupper($this->input('fun_rg_uf')) : null,
        ]);
    }

    public function rules(): array
    {
        $funId    = $this->route('funcionario')?->fun_id;
        $isCreate = $funId === null;
        $anoMax   = (int) date('Y') + 1;

        return [
            // Dados pessoais
            'fun_nome'        => ['required', 'string', 'max:100'],
            'fun_nome_social' => ['nullable', 'string', 'max:100'],
            'fun_dt_nascimento' => ['required', 'date', 'before:today'],
            'fun_sexo' => ['required', Rule::in(['M', 'F'])],
            'fun_cor_raca' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
            'fun_nacionalidade' => ['required', 'string', 'max:60'],
            'fun_pais_origem' => ['required', 'string', 'max:60'],
            'fun_mun_id_nasc' => ['required', 'integer', 'exists:edu_municipio,mun_id'],
            'fun_cpf' => ['required', 'digits:11', new Cpf],
            'fun_religiao' => ['nullable', 'string', 'max:60'],
            'fun_estado_civil' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'fun_povo_indigena' => ['nullable', 'string', 'max:60'],
            'fun_pin_id' => ['nullable', 'integer', 'exists:edu_povo_indigena,pin_id', 'required_if:fun_cor_raca,5'],
            'fun_cd_censo' => ['nullable', 'digits:12', Rule::unique('edu_funcionario', 'fun_cd_censo')->ignore($funId, 'fun_id')->whereNull('fun_deleted_at')],

            // Documentação
            'fun_rg_numero' => ['nullable', 'string', 'max:20'],
            'fun_rg_dt_emissao' => ['nullable', 'date'],
            'fun_rg_uf' => ['nullable', 'string', 'size:2'],
            'fun_rg_orgao_emissor' => ['nullable', 'string', 'max:20'],
            'fun_certidao_modelo' => ['nullable', 'string', 'max:30'],
            'fun_certidao_tipo' => ['nullable', 'string', 'max:30'],
            'fun_certidao_dt_emissao' => ['nullable', 'date'],
            'fun_certidao_numero' => ['nullable', 'string', 'max:32'],
            'fun_certidao_livro' => ['nullable', 'string', 'max:10'],
            'fun_certidao_pagina' => ['nullable', 'string', 'max:10'],
            'fun_certidao_mun_id' => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'fun_certidao_cartorio' => ['nullable', 'string', 'max:100'],
            'fun_ctps_numero' => ['nullable', 'string', 'max:20'],
            'fun_ctps_serie' => ['nullable', 'string', 'max:10'],
            'fun_pis_pasep' => ['nullable', 'string', 'max:20'],
            'fun_titulo_eleitor' => ['nullable', 'string', 'max:15'],
            'fun_titulo_zona' => ['nullable', 'string', 'max:5'],
            'fun_titulo_secao' => ['nullable', 'string', 'max:5'],
            'fun_certificado_reservista' => ['nullable', 'string', 'max:20'],

            // Endereço
            'fun_cep' => ['nullable', 'digits:8'],
            'fun_logradouro' => ['nullable', 'string', 'max:150'],
            'fun_numero' => ['nullable', 'string', 'max:10'],
            'fun_complemento' => ['nullable', 'string', 'max:100'],
            'fun_bairro' => ['nullable', 'string', 'max:100'],
            'fun_cidade' => ['nullable', 'string', 'max:100'],
            'fun_uf' => ['nullable', 'string', 'size:2'],

            // Contato
            'fun_telefone' => ['nullable', 'digits_between:10,11'],
            'fun_celular' => ['nullable', 'digits_between:10,11'],
            'fun_email' => ['nullable', 'email', 'max:150'],

            'fun_fl_usa_transporte' => ['boolean'],
            'fun_transporte_tipo' => ['nullable', 'string', 'max:30'],

            'fun_foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'fun_fl_ativo' => ['boolean'],

            // Escolaridades — aba própria; no cadastro é obrigatório informar ao menos uma.
            // Na edição, as escolaridades são gerenciadas por rotas próprias (não vêm no payload).
            'escolaridades' => [$isCreate ? 'required' : 'nullable', 'array', ...($isCreate ? ['min:1'] : [])],
            'escolaridades.*.fes_nivel' => ['required_with:escolaridades', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])],
            'escolaridades.*.fes_ano_inicio' => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax],
            'escolaridades.*.fes_ano_fim' => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax],
            'escolaridades.*.fes_cfs_id' => ['nullable', 'integer', 'exists:edu_curso_formacao_superior,cfs_id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Curso é obrigatório quando o nível é de ensino superior (5,6,7,8,10).
        $validator->after(function (Validator $v) {
            $niveisExigemCurso = [5, 6, 7, 8, 10];
            foreach ((array) $this->input('escolaridades', []) as $i => $esc) {
                $nivel = (int) ($esc['fes_nivel'] ?? 0);
                $curso = $esc['fes_cfs_id'] ?? null;
                if (in_array($nivel, $niveisExigemCurso, true) && ($curso === null || $curso === '')) {
                    $v->errors()->add("escolaridades.{$i}.fes_cfs_id", 'Selecione o curso para níveis de ensino superior.');
                }
                $ini = $esc['fes_ano_inicio'] ?? null;
                $fim = $esc['fes_ano_fim'] ?? null;
                if ($ini !== null && $ini !== '' && $fim !== null && $fim !== '' && (int) $fim < (int) $ini) {
                    $v->errors()->add("escolaridades.{$i}.fes_ano_fim", 'O ano final deve ser igual ou posterior ao ano inicial.');
                }
            }
        });

        $validator->after(function (Validator $v) {
            // Pula se CPF já tem erro de formato/validade
            if ($v->errors()->has('fun_cpf') || ! $this->filled('fun_cpf')) {
                return;
            }

            $cpf    = $this->input('fun_cpf');
            $funId  = $this->route('funcionario')?->fun_id;
            $dtNasc = $this->input('fun_dt_nascimento'); // formato Y-m-d

            // Duplicidade dentro de funcionários
            $existing = Funcionario::query()
                ->where('fun_cpf', $cpf)
                ->when($funId, fn ($q) => $q->where('fun_id', '!=', $funId))
                ->first(['fun_nome']);

            if ($existing) {
                $v->errors()->add('fun_cpf', "CPF já cadastrado para: {$existing->fun_nome}.");
                return; // já com erro, não precisa checar aluno
            }

            // Validação cruzada: CPF já existe em aluno?
            $aluno = Aluno::where('aln_cpf', $cpf)->first(['aln_nome', 'aln_dt_nascimento']);

            if ($aluno) {
                $dtAluno = $aluno->aln_dt_nascimento
                    ? $aluno->aln_dt_nascimento->format('Y-m-d')
                    : null;

                if ($dtAluno !== $dtNasc) {
                    $v->errors()->add(
                        'fun_cpf',
                        "CPF já cadastrado para o aluno {$aluno->aln_nome}. " .
                        'Para vincular o mesmo CPF, a data de nascimento deve ser idêntica.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fun_dt_nascimento.before' => 'A data de nascimento deve ser anterior à data de hoje.',
            'fun_pin_id.required_if'   => 'Selecione o povo indígena quando a etnia/cor/raça for Indígena.',
            'escolaridades.required'   => 'Informe ao menos uma escolaridade.',
            'escolaridades.min'        => 'Informe ao menos uma escolaridade.',
            'escolaridades.*.fes_nivel.required_with' => 'Selecione o nível de escolaridade.',
        ];
    }

    public function attributes(): array
    {
        return [
            'fun_nome'        => 'nome completo',
            'fun_nome_social' => 'nome social',
            'fun_dt_nascimento' => 'data de nascimento',
            'fun_sexo' => 'sexo',
            'fun_cor_raca' => 'etnia / cor / raça',
            'fun_nacionalidade' => 'nacionalidade',
            'fun_pais_origem' => 'país de origem',
            'fun_mun_id_nasc' => 'naturalidade (município)',
            'fun_cpf' => 'CPF',
            'fun_religiao' => 'religião',
            'fun_estado_civil' => 'estado civil',
            'fun_povo_indigena' => 'povo indígena',
            'fun_pin_id' => 'povo indígena',
            'fun_cd_censo' => 'código CENSO',
            'fun_rg_numero' => 'número do RG',
            'fun_rg_dt_emissao' => 'data de emissão do RG',
            'fun_rg_uf' => 'UF do RG',
            'fun_rg_orgao_emissor' => 'órgão emissor do RG',
            'fun_cep' => 'CEP',
            'fun_logradouro' => 'logradouro',
            'fun_numero' => 'número',
            'fun_complemento' => 'complemento',
            'fun_bairro' => 'bairro',
            'fun_cidade' => 'cidade',
            'fun_uf' => 'UF',
            'fun_telefone' => 'telefone',
            'fun_celular' => 'celular',
            'fun_email' => 'e-mail',
            'fun_foto' => 'foto',
        ];
    }
}
