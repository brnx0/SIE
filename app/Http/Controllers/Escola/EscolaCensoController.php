<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\UpdateCensoEscolarRequest;
use App\Models\Escola\CensoEscolar;
use App\Models\Escola\Escola;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EscolaCensoController extends Controller
{
    /**
     * Garante o (único) censo da escola e redireciona para edição.
     * O censo é um registro persistente por escola — não há duplicação por ano.
     */
    public function store(Escola $escola): RedirectResponse
    {
        $censo = CensoEscolar::firstOrCreate(['cen_esc_id' => $escola->esc_id]);

        return to_route('escolas.censo.edit', [$escola, $censo]);
    }

    /**
     * Exibe o formulário de edição do censo (sempre editável).
     */
    public function edit(Escola $escola, CensoEscolar $censoEscolar): Response
    {
        abort_if($censoEscolar->cen_esc_id !== $escola->esc_id, 403);

        return Inertia::render('escolas/censo/Edit', [
            'escola' => $escola->only(['esc_id', 'esc_nome', 'esc_apelido']),
            'censo'  => $censoEscolar,
        ]);
    }

    /**
     * Persiste as alterações do censo.
     */
    public function update(UpdateCensoEscolarRequest $request, Escola $escola, CensoEscolar $censoEscolar): RedirectResponse
    {
        abort_if($censoEscolar->cen_esc_id !== $escola->esc_id, 403);

        $censoEscolar->update($request->validated());

        return back()->with('success', 'Censo salvo com sucesso.');
    }
}
