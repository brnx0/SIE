<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Models\Parametro\GradeHorario;
use App\Models\Turma\TurmaHorario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GradeHorarioController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'grh_seg_id' => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'grh_turno'  => ['required', 'string', 'in:m,t,n'],
            'grh_hora'   => ['required', 'date_format:H:i'],
            'grh_ordem'  => ['required', 'integer', 'min:1', 'max:99'],
        ], [], [
            'grh_seg_id' => 'segmento',
            'grh_turno'  => 'turno',
            'grh_hora'   => 'horário',
            'grh_ordem'  => 'ordem',
        ]);

        $exists = GradeHorario::where('grh_seg_id', $data['grh_seg_id'])
            ->where('grh_turno', $data['grh_turno'])
            ->where('grh_hora', $data['grh_hora'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['grh_hora' => 'Este horário já está cadastrado para o segmento e turno.']);
        }

        GradeHorario::create($data);

        return to_route('parametros.edit')->with('success', 'Horário adicionado com sucesso.');
    }

    public function update(Request $request, GradeHorario $gradeHorario): RedirectResponse
    {
        $data = $request->validate([
            'grh_turno' => ['required', 'string', 'in:m,t,n'],
            'grh_hora'  => ['required', 'date_format:H:i'],
            'grh_ordem' => ['required', 'integer', 'min:1', 'max:99'],
        ], [], [
            'grh_turno' => 'turno',
            'grh_hora'  => 'horário',
            'grh_ordem' => 'ordem',
        ]);

        $exists = GradeHorario::where('grh_seg_id', $gradeHorario->grh_seg_id)
            ->where('grh_turno', $data['grh_turno'])
            ->where('grh_hora', $data['grh_hora'])
            ->where('grh_id', '!=', $gradeHorario->grh_id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['grh_hora' => 'Este horário já está cadastrado para o segmento e turno.']);
        }

        $gradeHorario->update($data);

        return to_route('parametros.edit')->with('success', 'Horário atualizado com sucesso.');
    }

    public function destroy(GradeHorario $gradeHorario): RedirectResponse
    {
        $emUso = TurmaHorario::withTrashed()
            ->where('trh_grh_id', $gradeHorario->grh_id)
            ->whereNull('trh_deleted_at')
            ->exists();

        if ($emUso) {
            return to_route('parametros.edit')
                ->with('error', 'Não é possível excluir este horário pois ele está sendo utilizado em grades de turmas.');
        }

        $gradeHorario->delete();

        return to_route('parametros.edit')->with('success', 'Horário removido com sucesso.');
    }
}
