<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaProfessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TurmaProfessorController extends Controller
{
    public function store(Request $request, Turma $turma): RedirectResponse
    {
        $data = $request->validate([
            'tup_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
            'tup_dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
        ], [], [
            'tup_fun_id' => 'professor',
            'tup_dis_id' => 'disciplina',
        ]);

        $exists = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $data['tup_fun_id'])
            ->where('tup_dis_id', $data['tup_dis_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['tup_dis_id' => 'Combinação professor/disciplina já cadastrada nesta turma.']);
        }

        $turma->professores()->create([
            'tup_tur_id' => $turma->tur_id,
            'tup_fun_id' => $data['tup_fun_id'],
            'tup_dis_id' => $data['tup_dis_id'],
        ]);

        return back()->with('success', 'Professor alocado com sucesso.');
    }

    public function destroy(Turma $turma, TurmaProfessor $turmaProfessor): RedirectResponse
    {
        $turmaProfessor->delete();

        return back()->with('success', 'Alocação removida com sucesso.');
    }
}
