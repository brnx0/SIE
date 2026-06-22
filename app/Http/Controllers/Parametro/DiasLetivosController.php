<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreDiasLetivosRequest;
use App\Models\Parametro\DiasLetivos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiasLetivosController extends Controller
{
    /** Upsert dos dias letivos por (segmento, ano). */
    public function salvar(StoreDiasLetivosRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Normaliza: mantém só valores numéricos não-nulos.
        $meses = collect($data['meses'] ?? [])
            ->map(fn ($v) => $v === null || $v === '' ? null : (int) $v)
            ->filter(fn ($v) => $v !== null)
            ->all();
        $periodos = collect($data['periodos'] ?? [])
            ->map(fn ($v) => $v === null || $v === '' ? null : (int) $v)
            ->filter(fn ($v) => $v !== null)
            ->all();

        DiasLetivos::updateOrCreate(
            ['dlt_anl_id' => $data['dlt_anl_id'], 'dlt_seg_id' => $data['dlt_seg_id']],
            [
                'dlt_meses'         => $meses,
                'dlt_periodos'      => $periodos,
                'dlt_created_by_id' => auth()->id(),
                'dlt_updated_by_id' => auth()->id(),
            ],
        );

        return to_route('parametros.edit')->with('success', 'Dias letivos salvos com sucesso.');
    }

    /** Replica os dias letivos do ano para vários segmentos (mesmo ano). */
    public function migrar(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'dlt_anl_id'   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'seg_ids'      => ['required', 'array', 'min:1'],
            'seg_ids.*'    => ['integer', 'exists:edu_segmento,seg_id'],
            'meses'        => ['array'],
            'meses.*'      => ['nullable', 'integer', 'min:0', 'max:31'],
            'periodos'     => ['array'],
            'periodos.*'   => ['nullable', 'integer', 'min:0', 'max:366'],
        ]);

        $meses = collect($data['meses'] ?? [])->map(fn ($v) => $v === null || $v === '' ? null : (int) $v)->filter(fn ($v) => $v !== null)->all();
        $periodos = collect($data['periodos'] ?? [])->map(fn ($v) => $v === null || $v === '' ? null : (int) $v)->filter(fn ($v) => $v !== null)->all();

        DB::transaction(function () use ($data, $meses, $periodos) {
            foreach (array_unique($data['seg_ids']) as $segId) {
                DiasLetivos::updateOrCreate(
                    ['dlt_anl_id' => $data['dlt_anl_id'], 'dlt_seg_id' => (int) $segId],
                    [
                        'dlt_meses'         => $meses,
                        'dlt_periodos'      => $periodos,
                        'dlt_created_by_id' => auth()->id(),
                        'dlt_updated_by_id' => auth()->id(),
                    ],
                );
            }
        });

        $n = count(array_unique($data['seg_ids']));

        return to_route('parametros.edit')->with('success', "Dias letivos replicados para {$n} segmento(s).");
    }
}
