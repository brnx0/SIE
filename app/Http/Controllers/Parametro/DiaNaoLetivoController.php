<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreDiaNaoLetivoRequest;
use App\Models\Parametro\DiaNaoLetivo;
use Illuminate\Http\RedirectResponse;

class DiaNaoLetivoController extends Controller
{
    public function store(StoreDiaNaoLetivoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['dnl_created_by_id'] = auth()->id();
        $data['dnl_updated_by_id'] = auth()->id();

        DiaNaoLetivo::create($data);

        return to_route('parametros.edit')->with('success', 'Dia não letivo cadastrado com sucesso.');
    }

    public function update(StoreDiaNaoLetivoRequest $request, DiaNaoLetivo $diaNaoLetivo): RedirectResponse
    {
        $data = $request->validated();
        $data['dnl_updated_by_id'] = auth()->id();

        $diaNaoLetivo->update($data);

        return to_route('parametros.edit')->with('success', 'Dia não letivo atualizado com sucesso.');
    }

    public function destroy(DiaNaoLetivo $diaNaoLetivo): RedirectResponse
    {
        $diaNaoLetivo->delete();

        return to_route('parametros.edit')->with('success', 'Dia não letivo removido com sucesso.');
    }
}
