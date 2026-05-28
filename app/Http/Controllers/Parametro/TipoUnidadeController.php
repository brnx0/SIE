<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreTipoUnidadeRequest;
use App\Models\Parametro\TipoUnidade;
use Illuminate\Http\RedirectResponse;

class TipoUnidadeController extends Controller
{
    public function store(StoreTipoUnidadeRequest $request): RedirectResponse
    {
        TipoUnidade::create($request->validated());

        return to_route('parametros.edit')->with('success', 'Tipo de unidade cadastrado com sucesso.');
    }

    public function update(StoreTipoUnidadeRequest $request, TipoUnidade $tipoUnidade): RedirectResponse
    {
        $tipoUnidade->update($request->validated());

        return to_route('parametros.edit')->with('success', 'Tipo de unidade atualizado com sucesso.');
    }

    public function destroy(TipoUnidade $tipoUnidade): RedirectResponse
    {
        $tipoUnidade->delete();

        return to_route('parametros.edit')->with('success', 'Tipo de unidade removido com sucesso.');
    }
}
