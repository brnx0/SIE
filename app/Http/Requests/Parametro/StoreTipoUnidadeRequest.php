<?php

namespace App\Http\Requests\Parametro;

use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\TipoUnidade;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoUnidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tun_tipo'           => ['required', 'string', Rule::in(array_keys(TipoUnidade::TIPOS))],
            'tun_anl_id_inicio'  => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tun_anl_id_fim'     => ['nullable', 'integer', 'exists:cfg_ano_letivo,anl_id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['tun_anl_id_inicio', 'tun_anl_id_fim'])) {
                return;
            }

            $inicioId = (int) $this->input('tun_anl_id_inicio');
            $fimId    = $this->filled('tun_anl_id_fim') ? (int) $this->input('tun_anl_id_fim') : null;
            $selfId   = $this->route('tipoUnidade')?->tun_id;

            $inicio   = AnoLetivo::find($inicioId);
            $fim      = $fimId ? AnoLetivo::find($fimId) : null;

            if (! $inicio) {
                return;
            }

            // Fim deve ser >= início
            if ($fim && $fim->anl_ano < $inicio->anl_ano) {
                $v->errors()->add('tun_anl_id_fim', 'O ano letivo fim deve ser igual ou posterior ao ano letivo início.');
                return;
            }

            $inicioAno = $inicio->anl_ano;
            $fimAno    = $fim?->anl_ano; // null = aberto

            // Verifica sobreposição com registros existentes
            $conflito = TipoUnidade::with(['anoLetivoInicio:anl_id,anl_ano', 'anoLetivoFim:anl_id,anl_ano'])
                ->when($selfId, fn ($q) => $q->where('tun_id', '!=', $selfId))
                ->get()
                ->first(function (TipoUnidade $reg) use ($inicioAno, $fimAno) {
                    $rIni = $reg->anoLetivoInicio?->anl_ano;
                    $rFim = $reg->anoLetivoFim?->anl_ano; // null = aberto

                    if (! $rIni) {
                        return false;
                    }

                    // Sobreposição: novo_ini <= reg_fim (ou reg_fim aberto) AND reg_ini <= novo_fim (ou novo_fim aberto)
                    $novoCobreReg  = ($fimAno === null || $fimAno >= $rIni);
                    $regCobreNovo  = ($rFim   === null || $rFim  >= $inicioAno);

                    return $novoCobreReg && $regCobreNovo;
                });

            if ($conflito) {
                $tipo  = TipoUnidade::TIPOS[$conflito->tun_tipo] ?? $conflito->tun_tipo;
                $de    = $conflito->anoLetivoInicio?->anl_ano ?? '?';
                $ate   = $conflito->anoLetivoFim?->anl_ano  ?? 'em aberto';
                $v->errors()->add(
                    'tun_anl_id_inicio',
                    "Período sobrepõe o registro \"{$tipo}\" de {$de} a {$ate}."
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'tun_tipo'          => 'tipo de unidade',
            'tun_anl_id_inicio' => 'ano letivo início',
            'tun_anl_id_fim'    => 'ano letivo fim',
        ];
    }
}
