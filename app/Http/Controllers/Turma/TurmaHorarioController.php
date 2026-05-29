<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Parametro\GradeHorario;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaHorario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Mpdf\Mpdf;

class TurmaHorarioController extends Controller
{
    private const DIAS = [
        'dom' => 'Domingo',
        'seg' => 'Segunda',
        'ter' => 'Terça',
        'qua' => 'Quarta',
        'qui' => 'Quinta',
        'sex' => 'Sexta',
        'sab' => 'Sábado',
    ];

    public function gradePdf(Turma $turma): HttpResponse
    {
        $turma->load([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'segmento:seg_id,seg_nome_reduzido',
            'serie:ser_id,ser_nome',
            'horarios.funcionario:fun_id,fun_nome',
            'horarios.disciplina:dis_id,dis_nome',
        ]);

        $turnoMap = ['MATUTINO' => 'm', 'VESPERTINO' => 't', 'NOTURNO' => 'n'];

        $grade = GradeHorario::where('grh_seg_id', $turma->tur_seg_id)
            ->when($turma->tur_turno !== 'INTEGRAL', fn ($q) => $q->where('grh_turno', $turnoMap[$turma->tur_turno] ?? $turma->tur_turno))
            ->orderBy('grh_ordem')
            ->get(['grh_id', 'grh_turno', 'grh_hora', 'grh_ordem']);

        $diasAtivos = collect(self::DIAS)
            ->filter(fn ($_, $k) => in_array($k, $turma->tur_dias_funcionamento ?? [], true))
            ->all();

        // Lookup: "{dia}:{grh_id}" => TurmaHorario
        $map = $turma->horarios->keyBy(fn ($h) => "{$h->trh_dia}:{$h->trh_grh_id}");

        $html = view('exports.turma_grade_pdf', [
            'turma'      => $turma,
            'grade'      => $grade,
            'diasAtivos' => $diasAtivos,
            'map'        => $map,
        ])->render();

        $filename = 'grade_horarios_' . $turma->tur_nome . '_' . now()->format('Ymd_His') . '.pdf';

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 12, 'margin_right' => 12, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function store(Request $request, Turma $turma): RedirectResponse
    {
        $data = $request->validate([
            'trh_grh_id' => ['required', 'integer', 'exists:edu_grade_horario,grh_id'],
            'trh_dia'    => ['required', 'string', 'in:dom,seg,ter,qua,qui,sex,sab'],
            'trh_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
            'trh_dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'trh_fl_tc'  => ['boolean'],
        ], [], [
            'trh_grh_id' => 'horário',
            'trh_dia'    => 'dia',
            'trh_fun_id' => 'professor',
            'trh_dis_id' => 'disciplina',
        ]);

        $data['trh_fl_tc'] = $request->boolean('trh_fl_tc');

        if (!$data['trh_fl_tc']) {
            $conflito = TurmaHorario::with('turma.escola')
                ->where('trh_grh_id', $data['trh_grh_id'])
                ->where('trh_dia', $data['trh_dia'])
                ->where('trh_fun_id', $data['trh_fun_id'])
                ->where('trh_fl_tc', false)
                ->whereHas('turma', fn ($q) =>
                    $q->where('tur_anl_id', $turma->tur_anl_id)
                      ->where('tur_id', '!=', $turma->tur_id)
                )
                ->first();

            if ($conflito) {
                $nome   = $conflito->turma->tur_nome;
                $escola = $conflito->turma->escola?->esc_nome ?? '';
                return back()->withErrors([
                    'trh_fun_id' => "Professor já alocado na turma {$nome} ({$escola}) neste horário.",
                ]);
            }
        }

        $turma->horarios()->create([
            'trh_tur_id' => $turma->tur_id,
            'trh_grh_id' => $data['trh_grh_id'],
            'trh_dia'    => $data['trh_dia'],
            'trh_fun_id' => $data['trh_fun_id'],
            'trh_dis_id' => $data['trh_dis_id'],
            'trh_fl_tc'  => $data['trh_fl_tc'],
        ]);

        return back()->with('success', 'Horário alocado com sucesso.');
    }

    public function destroy(Turma $turma, TurmaHorario $turmaHorario): RedirectResponse
    {
        $turmaHorario->delete();

        return back()->with('success', 'Horário removido com sucesso.');
    }
}
