<?php

namespace App\Http\Requests\Funcionario;

use App\Models\Funcionario\CursoFormacaoSuperior;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Curso de Formação Superior (tabela auxiliar INEP).
 *  - Código da área: 4 dígitos (área detalhada Cine Brasil, ex.: 0111).
 *  - Código do curso: 8 caracteres = área (4) + letra + 3 dígitos (ex.: 0111C012),
 *    e precisa começar pelo código da área informada.
 *  - Duplicidade: código do curso único; nome + nível + área únicos (sem caixa).
 *  - Um código de área só pode ter um nome (consistência com os demais cursos).
 */
class StoreCursoFormacaoSuperiorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cfs_cd_area'        => trim((string) $this->input('cfs_cd_area', '')),
            'cfs_area_detalhada' => trim((string) $this->input('cfs_area_detalhada', '')),
            'cfs_codigo'         => mb_strtoupper(trim((string) $this->input('cfs_codigo', '')), 'UTF-8'),
            'cfs_nome'           => trim((string) $this->input('cfs_nome', '')),
            'cfs_fl_ativo'       => $this->boolean('cfs_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('curso')?->cfs_id;

        return [
            'cfs_cd_area'        => ['required', 'regex:/^\d{4}$/'],
            'cfs_area_detalhada' => ['required', 'string', 'max:180'],
            'cfs_codigo'         => [
                'required', 'regex:/^\d{4}[A-Z]\d{3}$/',
                Rule::unique('edu_curso_formacao_superior', 'cfs_codigo')->ignore($id, 'cfs_id'),
            ],
            'cfs_nome'           => ['required', 'string', 'max:255'],
            'cfs_ngr_id'         => [
                'required', 'integer',
                // Nível inativo só é aceito se já for o nível salvo do curso.
                Rule::exists('edu_nivel_graduacao', 'ngr_id')->where(function ($q) use ($id) {
                    $atual = $id ? CursoFormacaoSuperior::whereKey($id)->value('cfs_ngr_id') : null;
                    $q->where(fn ($w) => $w->where('ngr_fl_ativo', true)->when($atual, fn ($x) => $x->orWhere('ngr_id', $atual)));
                }),
            ],
            'cfs_fl_ativo'       => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $id     = $this->route('curso')?->cfs_id;
            $area   = $this->input('cfs_cd_area');
            $codigo = $this->input('cfs_codigo');

            if (! str_starts_with($codigo, $area)) {
                $v->errors()->add('cfs_codigo', "O código do curso deve começar pelo código da área ({$area}).");

                return;
            }

            $nomeArea = DB::table('edu_curso_formacao_superior')
                ->where('cfs_cd_area', $area)
                ->when($id, fn ($q) => $q->where('cfs_id', '<>', $id))
                ->whereRaw('UPPER(cfs_area_detalhada) <> UPPER(?)', [$this->input('cfs_area_detalhada')])
                ->value('cfs_area_detalhada');

            if ($nomeArea) {
                $v->errors()->add('cfs_area_detalhada', "O código de área {$area} já está vinculado à área \"{$nomeArea}\".");
            }

            $duplicado = DB::table('edu_curso_formacao_superior')
                ->where('cfs_cd_area', $area)
                ->where('cfs_ngr_id', $this->input('cfs_ngr_id'))
                ->whereRaw('UPPER(cfs_nome) = UPPER(?)', [$this->input('cfs_nome')])
                ->when($id, fn ($q) => $q->where('cfs_id', '<>', $id))
                ->value('cfs_codigo');

            if ($duplicado) {
                $v->errors()->add('cfs_nome', "Já existe um curso com este nome, nível e área (código {$duplicado}).");
            }
        }];
    }

    public function messages(): array
    {
        return [
            'cfs_cd_area.regex' => 'O código INEP da área deve ter 4 dígitos (ex.: 0111).',
            'cfs_codigo.regex'  => 'O código INEP do curso deve ter 8 caracteres: 4 dígitos, 1 letra e 3 dígitos (ex.: 0111C012).',
            'cfs_codigo.unique' => 'Já existe um curso cadastrado com este código INEP.',
            'cfs_ngr_id.exists' => 'Selecione um nível de graduação ativo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cfs_cd_area'        => 'código INEP da área',
            'cfs_area_detalhada' => 'nome da área',
            'cfs_codigo'         => 'código INEP do curso',
            'cfs_nome'           => 'nome do curso',
            'cfs_ngr_id'         => 'nível de graduação acadêmica',
            'cfs_fl_ativo'       => 'situação',
        ];
    }
}
