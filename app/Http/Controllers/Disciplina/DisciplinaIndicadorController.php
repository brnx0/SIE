<?php

namespace App\Http\Controllers\Disciplina;

use App\Http\Controllers\Controller;
use App\Models\Disciplina\Disciplina;
use App\Models\Disciplina\DisciplinaIndicador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisciplinaIndicadorController extends Controller
{
    public function store(Request $request, Disciplina $disciplina): RedirectResponse
    {
        $data = $request->validate([
            'ind_descricao' => ['required', 'string', 'max:1000'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $disciplina->indicadores()->create($data);

        return back()->with('success', 'Indicador adicionado com sucesso.');
    }

    public function update(Request $request, Disciplina $disciplina, DisciplinaIndicador $indicador): RedirectResponse
    {
        abort_if($indicador->ind_dis_id !== $disciplina->dis_id, 404);

        $data = $request->validate([
            'ind_descricao' => ['required', 'string', 'max:1000'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $indicador->update($data);

        return back()->with('success', 'Indicador atualizado com sucesso.');
    }

    public function destroy(Disciplina $disciplina, DisciplinaIndicador $indicador): RedirectResponse
    {
        abort_if($indicador->ind_dis_id !== $disciplina->dis_id, 404);

        return $this->safeDelete($indicador)
            ?? back()->with('success', 'Indicador removido com sucesso.');
    }
}
