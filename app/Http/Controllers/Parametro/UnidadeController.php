<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreUnidadeRequest;
use App\Models\Parametro\Unidade;
use Illuminate\Http\RedirectResponse;

class UnidadeController extends Controller
{
    public function store(StoreUnidadeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['uni_numero'] = Unidade::where('uni_anl_id', $data['uni_anl_id'])->count() + 1;

        Unidade::create($data);

        return to_route('parametros.edit')->with('success', 'Período cadastrado com sucesso.');
    }

    public function update(StoreUnidadeRequest $request, Unidade $unidade): RedirectResponse
    {
        $unidade->update($request->validated());

        return to_route('parametros.edit')->with('success', 'Período atualizado com sucesso.');
    }

    public function destroy(Unidade $unidade): RedirectResponse
    {
        $hasNext = Unidade::where('uni_anl_id', $unidade->uni_anl_id)
            ->where('uni_numero', '>', $unidade->uni_numero)
            ->exists();

        if ($hasNext) {
            return back()->with('error', 'Exclua os períodos posteriores antes de remover este.');
        }

        $unidade->delete();

        return to_route('parametros.edit')->with('success', 'Período removido com sucesso.');
    }
}
