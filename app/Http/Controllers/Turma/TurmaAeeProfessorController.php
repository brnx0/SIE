<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaProfessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TurmaAeeProfessorController extends Controller
{
    /** Aloca professor na turma AEE (somente o professor, sem disciplina). */
    public function store(Request $request, Turma $turmaAee): RedirectResponse
    {
        abort_unless($turmaAee->tur_modalidade === Turma::MODALIDADE_AEE, 404);

        $data = $request->validate([
            'tup_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
        ], [], ['tup_fun_id' => 'professor']);

        $exists = TurmaProfessor::where('tup_tur_id', $turmaAee->tur_id)
            ->where('tup_fun_id', $data['tup_fun_id'])
            ->whereNull('tup_dis_id')
            ->whereNull('tup_deleted_at')
            ->exists();

        if ($exists) {
            return back()->withErrors(['tup_fun_id' => 'Professor já alocado nesta turma.']);
        }

        $turmaAee->professores()->create([
            'tup_tur_id' => $turmaAee->tur_id,
            'tup_fun_id' => $data['tup_fun_id'],
            'tup_dis_id' => null,
        ]);

        return back()->with('success', 'Professor alocado com sucesso.');
    }

    public function destroy(Turma $turmaAee, TurmaProfessor $turmaProfessor): RedirectResponse
    {
        abort_unless($turmaAee->tur_modalidade === Turma::MODALIDADE_AEE, 404);

        $turmaProfessor->delete();

        return back()->with('success', 'Alocação removida com sucesso.');
    }
}
