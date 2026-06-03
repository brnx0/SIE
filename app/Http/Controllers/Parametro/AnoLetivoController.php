<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreAnoLetivoRequest;
use App\Http\Requests\Parametro\UpdateAnoLetivoRequest;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\RedirectResponse;

class AnoLetivoController extends Controller
{
    public function store(StoreAnoLetivoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['anl_created_by_id'] = $request->user()->id;
        $data['anl_updated_by_id'] = $request->user()->id;

        AnoLetivo::create($data);

        return to_route('parametros.edit')->with('success', 'Ano letivo cadastrado com sucesso.');
    }

    public function update(UpdateAnoLetivoRequest $request, AnoLetivo $anoLetivo): RedirectResponse
    {
        $data = $request->validated();
        $data['anl_updated_by_id'] = $request->user()->id;

        $anoLetivo->update($data);

        return to_route('parametros.edit')->with('success', 'Ano letivo atualizado com sucesso.');
    }

    public function destroy(AnoLetivo $anoLetivo): RedirectResponse
    {
        return $this->safeDelete($anoLetivo)
            ?? to_route('parametros.edit')->with('success', 'Ano letivo removido com sucesso.');
    }
}
