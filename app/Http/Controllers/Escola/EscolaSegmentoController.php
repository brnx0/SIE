<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\StoreEscolaSegmentoRequest;
use App\Models\Escola\Escola;
use App\Models\Escola\EscolaSegmento;
use Illuminate\Http\RedirectResponse;

class EscolaSegmentoController extends Controller
{
    public function store(StoreEscolaSegmentoRequest $request, Escola $escola): RedirectResponse
    {
        $escola->segmentos()->create($request->validated());

        return to_route('escolas.edit', $escola)
            ->with('success', 'Segmento adicionado com sucesso.');
    }

    public function update(StoreEscolaSegmentoRequest $request, Escola $escola, int $esg): RedirectResponse
    {
        $escolaSegmento = EscolaSegmento::where('esg_id', $esg)
            ->where('esc_id', $escola->esc_id)
            ->firstOrFail();

        $escolaSegmento->update($request->validated());

        return to_route('escolas.edit', $escola)
            ->with('success', 'Segmento atualizado com sucesso.');
    }

    public function destroy(Escola $escola, int $esg): RedirectResponse
    {
        $escolaSegmento = EscolaSegmento::where('esg_id', $esg)
            ->where('esc_id', $escola->esc_id)
            ->firstOrFail();

        return $this->safeDelete($escolaSegmento)
            ?? to_route('escolas.edit', $escola)->with('success', 'Segmento removido com sucesso.');
    }
}
