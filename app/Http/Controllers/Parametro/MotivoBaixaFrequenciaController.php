<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreMotivoBaixaFrequenciaRequest;
use App\Http\Requests\Parametro\UpdateMotivoBaixaFrequenciaRequest;
use App\Models\Parametro\MotivoBaixaFrequencia;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MotivoBaixaFrequenciaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('secretaria/motivos-baixa-frequencia/Index', [
            'motivos' => MotivoBaixaFrequencia::orderBy('mbf_id')
                ->get(['mbf_id', 'mbf_descricao', 'mbf_fl_abona', 'mbf_fl_ativo']),
        ]);
    }

    public function store(StoreMotivoBaixaFrequenciaRequest $request): RedirectResponse
    {
        MotivoBaixaFrequencia::create([
            'mbf_descricao' => trim($request->input('mbf_descricao')),
            'mbf_fl_abona'  => $request->boolean('mbf_fl_abona', true),
            'mbf_fl_ativo'  => $request->boolean('mbf_fl_ativo', true),
        ]);

        return to_route('secretaria.motivos-baixa-frequencia.index')->with('success', 'Motivo cadastrado com sucesso.');
    }

    public function update(UpdateMotivoBaixaFrequenciaRequest $request, MotivoBaixaFrequencia $motivo): RedirectResponse
    {
        $motivo->update([
            'mbf_descricao' => trim($request->input('mbf_descricao')),
            'mbf_fl_abona'  => $request->boolean('mbf_fl_abona', $motivo->mbf_fl_abona),
            'mbf_fl_ativo'  => $request->boolean('mbf_fl_ativo', $motivo->mbf_fl_ativo),
        ]);

        return to_route('secretaria.motivos-baixa-frequencia.index')->with('success', 'Motivo atualizado com sucesso.');
    }

    public function destroy(MotivoBaixaFrequencia $motivo): RedirectResponse
    {
        return $this->safeDelete($motivo)
            ?? to_route('secretaria.motivos-baixa-frequencia.index')->with('success', 'Motivo removido com sucesso.');
    }
}
