<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaProfessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TurmaAtividadeProfessorController extends Controller
{
    public function store(Request $request, Turma $turmaAtividade): RedirectResponse
    {
        abort_unless($turmaAtividade->tur_modalidade === Turma::MODALIDADE_ATIVIDADE, 404);

        $data = $request->validate([
            'tup_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
        ], [], ['tup_fun_id' => 'professor']);

        $exists = TurmaProfessor::where('tup_tur_id', $turmaAtividade->tur_id)
            ->where('tup_fun_id', $data['tup_fun_id'])
            ->whereNull('tup_dis_id')
            ->whereNull('tup_deleted_at')
            ->exists();

        if ($exists) {
            return back()->withErrors(['tup_fun_id' => 'Professor já alocado nesta turma.']);
        }

        $turmaAtividade->professores()->create([
            'tup_tur_id' => $turmaAtividade->tur_id,
            'tup_fun_id' => $data['tup_fun_id'],
            'tup_dis_id' => null,
        ]);

        return back()->with('success', 'Professor alocado com sucesso.');
    }

    public function destroy(Turma $turmaAtividade, TurmaProfessor $turmaProfessor): RedirectResponse
    {
        abort_unless($turmaAtividade->tur_modalidade === Turma::MODALIDADE_ATIVIDADE, 404);

        $turmaProfessor->delete();

        return back()->with('success', 'Alocação removida com sucesso.');
    }
}
