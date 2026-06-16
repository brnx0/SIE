<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreConceitoRequest;
use App\Models\Parametro\Conceito;
use Illuminate\Http\RedirectResponse;

class ConceitoController extends Controller
{
    public function store(StoreConceitoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['cnc_created_by_id'] = auth()->id();
        $data['cnc_updated_by_id'] = auth()->id();

        Conceito::create($data);

        return to_route('parametros.edit')->with('success', 'Conceito cadastrado com sucesso.');
    }

    public function update(StoreConceitoRequest $request, Conceito $conceito): RedirectResponse
    {
        $data = $request->validated();
        $data['cnc_updated_by_id'] = auth()->id();

        $conceito->update($data);

        return to_route('parametros.edit')->with('success', 'Conceito atualizado com sucesso.');
    }

    public function destroy(Conceito $conceito): RedirectResponse
    {
        $conceito->delete();

        return to_route('parametros.edit')->with('success', 'Conceito removido com sucesso.');
    }
}
