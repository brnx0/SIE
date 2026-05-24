<?php

namespace App\Http\Requests\Escola;

use App\Models\Parametro\ParametroEntidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEscolaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $strip = fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : $v;
        $params = ParametroEntidade::current();

        $nome = $this->input('esc_nome');
        $apelido = $this->input('esc_apelido');
        if ($params->par_fl_nome_escola_caixa_alta) {
            if (is_string($nome)) {
                $nome = mb_strtoupper($nome, 'UTF-8');
            }
            if (is_string($apelido)) {
                $apelido = mb_strtoupper($apelido, 'UTF-8');
            }
        }

        $this->merge([
            'esc_nome' => $nome,
            'esc_apelido' => $apelido,
            'esc_cnpj' => $this->filled('esc_cnpj') ? $strip($this->input('esc_cnpj')) : null,
            'esc_cd_inep' => $this->filled('esc_cd_inep') ? $strip($this->input('esc_cd_inep')) : null,
            'esc_cep' => $this->filled('esc_cep') ? $strip($this->input('esc_cep')) : null,
            'esc_telefone_fixo' => $this->filled('esc_telefone_fixo') ? $strip($this->input('esc_telefone_fixo')) : null,
            'esc_fax' => $this->filled('esc_fax') ? $strip($this->input('esc_fax')) : null,
            'esc_telefone_2' => $this->filled('esc_telefone_2') ? $strip($this->input('esc_telefone_2')) : null,
            'esc_telefone_3' => $this->filled('esc_telefone_3') ? $strip($this->input('esc_telefone_3')) : null,
            'esc_ddd' => $this->filled('esc_ddd') ? $strip($this->input('esc_ddd')) : null,
            'esc_zona' => $this->filled('esc_zona') ? strtoupper($this->input('esc_zona')) : null,
        ]);
    }

    public function rules(): array
    {
        $escolaId = $this->route('escola')?->esc_id;

        return [
            // Identificação
            'esc_cd_inep' => ['required', 'digits:8', Rule::unique('edu_escola', 'esc_cd_inep')->ignore($escolaId, 'esc_id')->whereNull('esc_deleted_at')],
            'esc_cnpj' => ['required', 'digits:14', Rule::unique('edu_escola', 'esc_cnpj')->ignore($escolaId, 'esc_id')->whereNull('esc_deleted_at')],
            'esc_nome' => ['required', 'string', 'max:150'],
            'esc_apelido' => ['required', 'string', 'max:100'],
            'esc_cd_escola' => ['nullable', 'string', 'max:20'],
            'esc_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Endereço
            'esc_cep' => ['required', 'digits:8'],
            'esc_logradouro' => ['required', 'string', 'max:150'],
            'esc_numero' => ['required', 'string', 'max:10'],
            'esc_complemento' => ['nullable', 'string', 'max:100'],
            'esc_bai_id' => ['required', 'integer', 'exists:edu_bairro,bai_id'],
            'esc_mun_id' => ['required', 'integer', 'exists:edu_municipio,mun_id'],
            'esc_zona' => ['required', Rule::in(['U', 'R'])],
            'esc_localizacao_dif' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6, 7])],
            'esc_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'esc_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'esc_caixa_postal' => ['nullable', 'string', 'max:20'],

            // Contato
            'esc_ddd' => ['nullable', 'digits:2'],
            'esc_telefone_fixo' => ['nullable', 'digits_between:8,11'],
            'esc_fax' => ['nullable', 'digits_between:8,11'],
            'esc_telefone_2' => ['nullable', 'digits_between:8,11'],
            'esc_telefone_3' => ['nullable', 'digits_between:8,11'],
            'esc_email' => ['required', 'email', 'max:150'],
            'esc_site' => ['nullable', 'url', 'max:200'],

            // Administrativo
            'esc_dep_administrativa' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'esc_proprietario_imovel' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'esc_forma_ocupacao' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'esc_situacao_func' => ['required', 'integer', Rule::in([1, 2, 3])],
            'esc_regulamentada_conselho' => ['nullable', 'boolean'],
            'esc_turno_escolar' => ['nullable', 'string', 'max:20'],
            'esc_ger_id' => ['nullable', 'integer', 'exists:edu_gerencia_regional,ger_id'],
            'esc_orgao_regional_ensino' => ['nullable', 'string', 'max:120'],
            'esc_fl_creche' => ['boolean'],
            'esc_fl_predio_compartilhado' => ['boolean'],
            'esc_fl_sorteio_vagas' => ['boolean'],
            'esc_fl_ativo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'esc_cd_inep' => 'código Censo (INEP)',
            'esc_cnpj' => 'CNPJ',
            'esc_nome' => 'nome',
            'esc_apelido' => 'apelido',
            'esc_cd_escola' => 'código da escola',
            'esc_logo' => 'logo',
            'esc_cep' => 'CEP',
            'esc_logradouro' => 'endereço',
            'esc_numero' => 'número',
            'esc_complemento' => 'complemento',
            'esc_bai_id' => 'bairro',
            'esc_mun_id' => 'cidade / UF',
            'esc_zona' => 'zona',
            'esc_localizacao_dif' => 'localização diferenciada',
            'esc_latitude' => 'latitude',
            'esc_longitude' => 'longitude',
            'esc_caixa_postal' => 'caixa postal',
            'esc_ddd' => 'DDD',
            'esc_telefone_fixo' => 'telefone fixo',
            'esc_fax' => 'fax',
            'esc_telefone_2' => 'telefone 2',
            'esc_telefone_3' => 'telefone 3',
            'esc_email' => 'e-mail',
            'esc_site' => 'site',
            'esc_dep_administrativa' => 'dependência administrativa',
            'esc_proprietario_imovel' => 'proprietário do imóvel',
            'esc_forma_ocupacao' => 'forma de ocupação',
            'esc_situacao_func' => 'situação de funcionamento',
            'esc_regulamentada_conselho' => 'regulamentada pelo conselho',
            'esc_turno_escolar' => 'turno escolar',
            'esc_ger_id' => 'gerência regional',
            'esc_orgao_regional_ensino' => 'órgão regional de ensino',
            'esc_fl_creche' => 'creche',
            'esc_fl_predio_compartilhado' => 'prédio compartilhado',
            'esc_fl_sorteio_vagas' => 'participa do sorteio de vagas',
        ];
    }
}
