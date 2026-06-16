<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreSituacaoBloqueioRequest;
use App\Models\Parametro\SituacaoBloqueio;
use Illuminate\Http\RedirectResponse;

class SituacaoBloqueioController extends Controller
{
    public function store(StoreSituacaoBloqueioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sba_created_by_id'] = auth()->id();

        SituacaoBloqueio::create($data);

        return to_route('parametros.edit')->with('success', 'Situação de bloqueio adicionada com sucesso.');
    }

    public function destroy(SituacaoBloqueio $situacaoBloqueio): RedirectResponse
    {
        $situacaoBloqueio->delete();

        return to_route('parametros.edit')->with('success', 'Situação de bloqueio removida com sucesso.');
    }
}
