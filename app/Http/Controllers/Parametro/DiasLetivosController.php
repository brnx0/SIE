<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreDiasLetivosRequest;
use App\Models\Parametro\DiasLetivos;
use Illuminate\Http\RedirectResponse;

class DiasLetivosController extends Controller
{
    /** Upsert dos dias letivos do ano (único para toda a rede). */
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
            ['dlt_anl_id' => $data['dlt_anl_id']],
            [
                'dlt_meses'         => $meses,
                'dlt_periodos'      => $periodos,
                'dlt_created_by_id' => auth()->id(),
                'dlt_updated_by_id' => auth()->id(),
            ],
        );

        return to_route('parametros.edit')->with('success', 'Dias letivos salvos com sucesso.');
    }
}
