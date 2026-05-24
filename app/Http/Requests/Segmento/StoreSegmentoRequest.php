<?php

namespace App\Http\Requests\Segmento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSegmentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parseDate = function (?string $v): ?string {
            if (!$v) return null;
            $m = preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $p);
            return $m ? "{$p[3]}-{$p[2]}-{$p[1]}" : $v;
        };

        $this->merge([
            'seg_dt_abertura'     => $parseDate($this->input('seg_dt_abertura')),
            'seg_dt_encerramento' => $parseDate($this->input('seg_dt_encerramento')),
            'seg_fl_prereq'       => $this->boolean('seg_fl_prereq'),
            'seg_fl_ativo'        => $this->boolean('seg_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        $segmentoId = $this->route('segmento')?->seg_id;

        return [
            'seg_cd_inep'          => ['nullable', 'string', 'max:20', Rule::unique('edu_segmento', 'seg_cd_inep')->ignore($segmentoId, 'seg_id')->whereNull('seg_deleted_at')],
            'seg_nome_reduzido'    => ['required', 'string', 'max:60', Rule::unique('edu_segmento', 'seg_nome_reduzido')->ignore($segmentoId, 'seg_id')->whereNull('seg_deleted_at')],
            'seg_nome_completo'    => ['required', 'string', 'max:150', Rule::unique('edu_segmento', 'seg_nome_completo')->ignore($segmentoId, 'seg_id')->whereNull('seg_deleted_at')],
            'seg_qt_anos_escolares'=> ['required', 'integer', 'min:1', 'max:99'],
            'seg_ordem'            => ['required', 'integer', 'min:1', 'max:999'],
            'seg_dt_abertura'      => ['required', 'date'],
            'seg_dt_encerramento'  => ['nullable', 'date', 'after_or_equal:seg_dt_abertura'],
            'seg_fl_prereq'        => ['boolean'],
            'seg_ds_prereq'        => ['nullable', 'string', 'required_if:seg_fl_prereq,true'],
            'seg_observacoes'      => ['nullable', 'string'],
            'seg_fl_ativo'         => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'seg_cd_inep'           => 'código INEP',
            'seg_nome_reduzido'     => 'nome reduzido',
            'seg_nome_completo'     => 'nome completo',
            'seg_qt_anos_escolares' => 'quantidade de anos escolares',
            'seg_ordem'             => 'ordem',
            'seg_dt_abertura'       => 'data de abertura',
            'seg_dt_encerramento'   => 'data de encerramento',
            'seg_ds_prereq'         => 'pré-requisito',
            'seg_observacoes'       => 'observações',
        ];
    }
}
