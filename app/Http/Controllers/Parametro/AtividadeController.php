<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreAtividadeRequest;
use App\Http\Requests\Parametro\UpdateAtividadeRequest;
use App\Models\Parametro\Atividade;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AtividadeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('atividades/Index', [
            'atividades' => Atividade::orderBy('atv_descricao')
                ->get(['atv_id', 'atv_descricao', 'atv_fl_ativo']),
        ]);
    }

    public function store(StoreAtividadeRequest $request): RedirectResponse
    {
        Atividade::create([
            'atv_descricao' => trim($request->input('atv_descricao')),
            'atv_fl_ativo'  => $request->boolean('atv_fl_ativo', true),
        ]);

        return to_route('atividades.index')->with('success', 'Atividade cadastrada com sucesso.');
    }

    public function update(UpdateAtividadeRequest $request, Atividade $atividade): RedirectResponse
    {
        $atividade->update([
            'atv_descricao' => trim($request->input('atv_descricao')),
            'atv_fl_ativo'  => $request->boolean('atv_fl_ativo', $atividade->atv_fl_ativo),
        ]);

        return to_route('atividades.index')->with('success', 'Atividade atualizada com sucesso.');
    }

    public function destroy(Atividade $atividade): RedirectResponse
    {
        return $this->safeDelete($atividade)
            ?? to_route('atividades.index')->with('success', 'Atividade removida com sucesso.');
    }
}
