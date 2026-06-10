<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaHorario;
use App\Models\Turma\TurmaProfessor;
use App\Models\Turma\TurmaProfessorApoio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $apoio = TurmaProfessorApoio::where('tpa_tur_id', $turma->tur_id)
            ->where('tpa_fun_id', $data['tup_fun_id'])
            ->exists();

        if ($apoio) {
            return back()->withErrors(['tup_fun_id' => 'Este professor já está alocado como apoio nesta turma. Remova da lista de apoio para alocá-lo como regente.']);
        }

        $temPerfil = Funcionario::where('fun_id', $data['tup_fun_id'])
            ->whereHas('admissoes.lotacoes', fn ($q) =>
                $q->where('lot_esc_id', $turma->tur_esc_id)
                  ->whereJsonContains('lot_funcoes_sala_aula', 'Docente')
                  ->whereHas('cargo', fn ($c) => $c->where('crg_nome', 'ilike', 'Professor%'))
            )->exists();

        if (! $temPerfil) {
            return back()->withErrors([
                'tup_fun_id' => 'Professor precisa ter cargo "Professor*" e função em sala de aula "Docente" em lotação ativa nesta escola.',
            ]);
        }

        $turma->professores()->create([
            'tup_tur_id' => $turma->tur_id,
            'tup_fun_id' => $data['tup_fun_id'],
            'tup_dis_id' => $data['tup_dis_id'],
        ]);

        return back()->with('success', 'Professor alocado com sucesso.');
    }

    public function update(Request $request, Turma $turma, TurmaProfessor $turmaProfessor): RedirectResponse
    {
        $data = $request->validate([
            'tup_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
            'tup_dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
        ], [], [
            'tup_fun_id' => 'professor',
            'tup_dis_id' => 'disciplina',
        ]);

        $duplic = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $data['tup_fun_id'])
            ->where('tup_dis_id', $data['tup_dis_id'])
            ->where('tup_id', '!=', $turmaProfessor->tup_id)
            ->exists();

        if ($duplic) {
            return back()->withErrors(['tup_dis_id' => 'Combinação professor/disciplina já cadastrada nesta turma.']);
        }

        $apoio = TurmaProfessorApoio::where('tpa_tur_id', $turma->tur_id)
            ->where('tpa_fun_id', $data['tup_fun_id'])
            ->exists();

        if ($apoio) {
            return back()->withErrors(['tup_fun_id' => 'Este professor já está alocado como apoio nesta turma. Remova da lista de apoio para alocá-lo como regente.']);
        }

        $temPerfil = Funcionario::where('fun_id', $data['tup_fun_id'])
            ->whereHas('admissoes.lotacoes', fn ($q) =>
                $q->where('lot_esc_id', $turma->tur_esc_id)
                  ->whereJsonContains('lot_funcoes_sala_aula', 'Docente')
                  ->whereHas('cargo', fn ($c) => $c->where('crg_nome', 'ilike', 'Professor%'))
            )->exists();

        if (! $temPerfil) {
            return back()->withErrors([
                'tup_fun_id' => 'Professor precisa ter cargo "Professor*" e função em sala de aula "Docente" em lotação ativa nesta escola.',
            ]);
        }

        $turmaProfessor->update([
            'tup_fun_id' => $data['tup_fun_id'],
            'tup_dis_id' => $data['tup_dis_id'],
        ]);

        return back()->with('success', 'Alocação atualizada com sucesso.');
    }

    public function destroy(Turma $turma, TurmaProfessor $turmaProfessor): RedirectResponse
    {
        DB::transaction(function () use ($turma, $turmaProfessor) {
            // Libera slots de horário desta combinação professor+disciplina nesta turma.
            // forceDelete: unique constraint (trh_tur_id, trh_tempo, trh_dia) ignora soft delete.
            TurmaHorario::withTrashed()
                ->where('trh_tur_id', $turma->tur_id)
                ->where('trh_fun_id', $turmaProfessor->tup_fun_id)
                ->where('trh_dis_id', $turmaProfessor->tup_dis_id)
                ->forceDelete();

            $turmaProfessor->forceDelete();
        });

        return back()->with('success', 'Alocação removida com sucesso.');
    }
}
