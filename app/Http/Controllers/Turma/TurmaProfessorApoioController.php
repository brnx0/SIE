<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaProfessor;
use App\Models\Turma\TurmaProfessorApoio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TurmaProfessorApoioController extends Controller
{
    public function store(Request $request, Turma $turma): RedirectResponse
    {
        $data = $request->validate([
            'tpa_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
            'tpa_obs'    => ['nullable', 'string', 'max:500'],
        ], [], [
            'tpa_fun_id' => 'professor',
            'tpa_obs'    => 'observação',
        ]);

        $duplicado = TurmaProfessorApoio::where('tpa_tur_id', $turma->tur_id)
            ->where('tpa_fun_id', $data['tpa_fun_id'])
            ->exists();

        if ($duplicado) {
            return back()->withErrors([
                'tpa_fun_id' => 'Este professor já está alocado como apoio nesta turma.',
            ]);
        }

        $regente = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $data['tpa_fun_id'])
            ->exists();

        if ($regente) {
            return back()->withErrors([
                'tpa_fun_id' => 'Este professor já está alocado como regente nesta turma. Remova da lista de regentes para alocá-lo como apoio.',
            ]);
        }

        $turma->professoresApoio()->create([
            'tpa_tur_id' => $turma->tur_id,
            'tpa_fun_id' => $data['tpa_fun_id'],
            'tpa_obs'    => $data['tpa_obs'] ?? null,
        ]);

        return back()->with('success', 'Professor de apoio alocado com sucesso.');
    }

    public function update(Request $request, Turma $turma, TurmaProfessorApoio $apoio): RedirectResponse
    {
        $data = $request->validate([
            'tpa_obs' => ['nullable', 'string', 'max:500'],
        ], [], [
            'tpa_obs' => 'observação',
        ]);

        $apoio->update(['tpa_obs' => $data['tpa_obs'] ?? null]);

        return back()->with('success', 'Observação atualizada.');
    }

    public function destroy(Turma $turma, TurmaProfessorApoio $apoio): RedirectResponse
    {
        $apoio->delete();

        return back()->with('success', 'Professor de apoio removido com sucesso.');
    }
}
