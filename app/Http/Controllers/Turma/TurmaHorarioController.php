<?php

namespace App\Http\Controllers\Turma;

use App\Http\Controllers\Controller;
use App\Models\Parametro\GradeHorario;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaHorario;
use App\Models\Turma\TurmaProfessor;
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
            'trh_tempo'  => ['required', 'integer', 'min:1', 'max:10'],
            'trh_hora'   => ['nullable', 'date_format:H:i'],
            'trh_dia'    => ['required', 'string', 'in:dom,seg,ter,qua,qui,sex,sab'],
            'trh_fun_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
            'trh_dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'trh_fl_tc'  => ['boolean'],
        ], [], [
            'trh_tempo'  => 'tempo',
            'trh_hora'   => 'horário',
            'trh_dia'    => 'dia',
            'trh_fun_id' => 'professor',
            'trh_dis_id' => 'disciplina',
        ]);

        $data['trh_fl_tc'] = $request->boolean('trh_fl_tc');

        // Professor + disciplina devem estar alocados na turma (aba Professores).
        $alocado = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $data['trh_fun_id'])
            ->where('tup_dis_id', $data['trh_dis_id'])
            ->exists();

        if (! $alocado) {
            return back()->withErrors([
                'trh_fun_id' => 'Professor não está alocado nesta turma com a disciplina informada. Aloque na aba Professores antes.',
            ]);
        }

        $conflito = TurmaHorario::with('turma')
            ->where('trh_tempo', $data['trh_tempo'])
            ->where('trh_dia', $data['trh_dia'])
            ->where('trh_fun_id', $data['trh_fun_id'])
            ->whereHas('turma', fn ($q) =>
                $q->where('tur_anl_id', $turma->tur_anl_id)
                  ->where('tur_id', '!=', $turma->tur_id)
            )
            ->first();

        if ($conflito) {
            $mesmaEscola = $conflito->turma->tur_esc_id === $turma->tur_esc_id;
            $bloquear = $data['trh_fl_tc'] ? !$mesmaEscola : true;

            if ($bloquear) {
                $nome   = $conflito->turma->tur_nome;
                $escola = $mesmaEscola ? 'nesta escola' : 'em outra escola';
                return back()->withErrors([
                    'trh_fun_id' => "Professor já alocado neste {$data['trh_tempo']}º tempo na {$data['trh_dia']} na turma {$nome} ({$escola}).",
                ]);
            }
        }

        // Upsert no slot (trh_tur_id, trh_tempo, trh_dia) — re-aloca sem violar unique.
        // withTrashed: unique constraint atinge linhas soft-deleted; precisa restaurar/atualizar.
        $existing = TurmaHorario::withTrashed()
            ->where('trh_tur_id', $turma->tur_id)
            ->where('trh_tempo', $data['trh_tempo'])
            ->where('trh_dia', $data['trh_dia'])
            ->first();

        $payload = [
            'trh_grh_id' => null,
            'trh_hora'   => $data['trh_hora'] ?? null,
            'trh_fun_id' => $data['trh_fun_id'],
            'trh_dis_id' => $data['trh_dis_id'],
            'trh_fl_tc'  => $data['trh_fl_tc'],
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->trh_deleted_at = null;
            $existing->save();
        } else {
            TurmaHorario::create(array_merge($payload, [
                'trh_tur_id' => $turma->tur_id,
                'trh_tempo'  => $data['trh_tempo'],
                'trh_dia'    => $data['trh_dia'],
            ]));
        }

        return back()->with('success', 'Horário alocado com sucesso.');
    }

    public function destroy(Turma $turma, TurmaHorario $turmaHorario): RedirectResponse
    {
        // forceDelete: unique constraint do slot ignora soft delete.
        $turmaHorario->forceDelete();

        return back()->with('success', 'Horário removido com sucesso.');
    }
}
