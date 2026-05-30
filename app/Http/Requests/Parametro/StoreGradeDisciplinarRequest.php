<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\GradeDisciplinar;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreGradeDisciplinarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grd_anl_id'           => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'grd_seg_id'           => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'grd_ser_id'           => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'grd_dis_id'           => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'grd_nome_alternativo' => ['nullable', 'string', 'max:100'],
            'grd_fl_ativo'         => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->count()) {
                return;
            }

            $anlId  = (int) $this->input('grd_anl_id');
            $segId  = (int) $this->input('grd_seg_id');
            $serId  = (int) $this->input('grd_ser_id');
            $disId  = (int) $this->input('grd_dis_id');
            $selfId = $this->route('grade')?->grd_id;

            $dup = GradeDisciplinar::where('grd_anl_id', $anlId)
                ->where('grd_seg_id', $segId)
                ->where('grd_ser_id', $serId)
                ->where('grd_dis_id', $disId)
                ->when($selfId, fn ($q) => $q->where('grd_id', '!=', $selfId))
                ->exists();

            if ($dup) {
                $v->errors()->add('grd_dis_id', 'Esta disciplina já está vinculada a esta grade (ano + segmento + série).');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'grd_anl_id'           => 'ano letivo',
            'grd_seg_id'           => 'segmento',
            'grd_ser_id'           => 'série',
            'grd_dis_id'           => 'disciplina',
            'grd_nome_alternativo' => 'nome alternativo',
            'grd_fl_ativo'         => 'ativo',
        ];
    }
}
