<?php

namespace App\Http\Requests\Parametro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametroEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'par_nome_entidade' => ['required', 'string', 'max:255'],
            'par_msg_cab_secretaria' => ['required', 'string', 'max:255'],
            'par_msg_cab_estado' => ['required', 'string', 'max:255'],
            'par_endereco' => ['required', 'string', 'max:255'],
            'par_mun_id' => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'par_logomarca' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'par_brasao' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],

            'par_fl_nome_pessoa_caixa_alta' => ['boolean'],
            'par_fl_nome_escola_caixa_alta' => ['boolean'],
            'par_fl_alertar_homonimos' => ['boolean'],
            'par_fl_alertar_acentos_nomes' => ['boolean'],
            'par_fl_validar_idade_serie' => ['boolean'],
            'par_fl_gerar_matricula_auto' => ['boolean'],
            'par_fl_validar_carga_prof' => ['boolean'],
            'par_fl_cpf_obrigatorio' => ['boolean'],
            'par_fl_fardamento_obrigatorio' => ['boolean'],
            'par_tipo_validacao_carga' => ['nullable', \Illuminate\Validation\Rule::in(['bloquear', 'avisar'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'par_nome_entidade' => 'nome da entidade',
            'par_msg_cab_secretaria' => 'mensagem cabeçalho (Secretaria)',
            'par_msg_cab_estado' => 'mensagem cabeçalho (Estado)',
            'par_endereco' => 'endereço',
            'par_mun_id' => 'cidade / UF',
            'par_logomarca' => 'logomarca',
            'par_brasao' => 'brasão',
        ];
    }
}
