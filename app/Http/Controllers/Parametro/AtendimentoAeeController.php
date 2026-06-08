<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreAtendimentoAeeRequest;
use App\Http\Requests\Parametro\UpdateAtendimentoAeeRequest;
use App\Models\Parametro\AtendimentoAee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AtendimentoAeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('atendimentos-aee/Index', [
            'atendimentosAee' => AtendimentoAee::orderBy('ate_descricao')
                ->get(['ate_id', 'ate_descricao', 'ate_fl_ativo']),
        ]);
    }

    public function store(StoreAtendimentoAeeRequest $request): RedirectResponse
    {
        AtendimentoAee::create([
            'ate_descricao' => trim($request->input('ate_descricao')),
            'ate_fl_ativo'  => $request->boolean('ate_fl_ativo', true),
        ]);

        return to_route('atendimentos-aee.index')->with('success', 'Atendimento AEE cadastrado com sucesso.');
    }

    public function update(UpdateAtendimentoAeeRequest $request, AtendimentoAee $atendimentoAee): RedirectResponse
    {
        $atendimentoAee->update([
            'ate_descricao' => trim($request->input('ate_descricao')),
            'ate_fl_ativo'  => $request->boolean('ate_fl_ativo', $atendimentoAee->ate_fl_ativo),
        ]);

        return to_route('atendimentos-aee.index')->with('success', 'Atendimento AEE atualizado com sucesso.');
    }

    public function destroy(AtendimentoAee $atendimentoAee): RedirectResponse
    {
        return $this->safeDelete($atendimentoAee)
            ?? to_route('atendimentos-aee.index')->with('success', 'Atendimento AEE removido com sucesso.');
    }
}
