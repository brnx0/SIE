<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Disciplina\Disciplina;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\GradeDisciplinar;
use App\Models\Parametro\GradeHorario;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaHorario;
use App\Models\Turma\TurmaProfessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuadroHorarioController extends Controller
{
    private const TURNO_MAP = [
        'MATUTINO'   => 'm',
        'VESPERTINO' => 't',
        'NOTURNO'    => 'n',
    ];

    public function index(Request $request): Response
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;

        $turma   = null;
        $horarios = [];
        $gradeHorarios = [];
        $professores = [];
        $disciplinas = [];

        $turId = (int) $request->input('tur_id');
        if ($turId) {
            $t = Turma::query()
                ->where('tur_id', $turId)
                ->with([
                    'escola:esc_id,esc_nome',
                    'anoLetivo:anl_id,anl_ano',
                    'segmento:seg_id,seg_nome_reduzido',
                    'serie:ser_id,ser_nome',
                    'professores.funcionario:fun_id,fun_nome',
                    'professores.disciplina:dis_id,dis_nome',
                    'horarios.funcionario:fun_id,fun_nome',
                    'horarios.disciplina:dis_id,dis_nome',
                ])
                ->first();

            if ($t) {
                $this->assertTurmaDoProfessor($t, $funId, $request);

                $turma         = $t;
                $horarios      = $t->horarios;
                $professores   = $t->professores;
                $gradeHorarios = GradeHorario::where('grh_seg_id', $t->tur_seg_id)
                    ->when($t->tur_turno !== 'INTEGRAL', fn ($q) => $q->where('grh_turno', self::TURNO_MAP[$t->tur_turno] ?? $t->tur_turno))
                    ->orderBy('grh_ordem')
                    ->get(['grh_id', 'grh_turno', 'grh_hora', 'grh_ordem']);
                $disciplinas   = Disciplina::whereIn('dis_id',
                        GradeDisciplinar::where('grd_anl_id', $t->tur_anl_id)
                            ->where('grd_ser_id', $t->tur_ser_id)
                            ->where('grd_fl_ativo', true)
                            ->pluck('grd_dis_id')
                    )->orderBy('dis_nome')->get(['dis_id', 'dis_nome']);
            }
        }

        return Inertia::render('diario/quadro-horario/Index', [
            'anosLetivos'   => $this->anosLetivosDoProfessor($funId),
            'turma'         => $turma,
            'horarios'      => $horarios,
            'gradeHorarios' => $gradeHorarios,
            'professores'   => $professores,
            'disciplinas'   => $disciplinas,
            'filters'       => [
                'anl_id' => (int) $request->input('anl_id') ?: null,
                'esc_id' => (int) $request->input('esc_id') ?: null,
                'tur_id' => $turId ?: null,
            ],
        ]);
    }

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;
        $anlId = (int) $request->input('anl_id');

        $escolas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->select('e.esc_id', 'e.esc_nome')
            ->distinct()
            ->orderBy('e.esc_nome')
            ->get();

        return response()->json($escolas);
    }

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $funId = (int) $request->user()->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        $turmas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escId, fn ($q) => $q->where('t.tur_esc_id', $escId))
            ->select('t.tur_id', 't.tur_nome', 's.ser_nome')
            ->distinct()
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get();

        return response()->json($turmas);
    }

    public function store(Request $request, Turma $turma): RedirectResponse
    {
        $this->abortIfNotProfessor();
        $this->assertTurmaDoProfessor($turma, (int) $request->user()->fun_id, $request);

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

        $alocado = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $data['trh_fun_id'])
            ->where('tup_dis_id', $data['trh_dis_id'])
            ->exists();

        if (! $alocado) {
            return back()->withErrors([
                'trh_fun_id' => 'Professor não está alocado nesta turma com a disciplina informada.',
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

    public function destroy(Request $request, Turma $turma, TurmaHorario $turmaHorario): RedirectResponse
    {
        $this->abortIfNotProfessor();
        $this->assertTurmaDoProfessor($turma, (int) $request->user()->fun_id, $request);

        abort_unless((int) $turmaHorario->trh_tur_id === (int) $turma->tur_id, 404);

        $turmaHorario->forceDelete();

        return back()->with('success', 'Horário removido com sucesso.');
    }

    // ============ Helpers ============

    private function abortIfNotProfessor(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito a professores.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor') && $user->fun_id, 403, 'Acesso restrito a professores.');
    }

    private function assertTurmaDoProfessor(Turma $turma, int $funId, Request $request): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }
        $alocado = TurmaProfessor::where('tup_tur_id', $turma->tur_id)
            ->where('tup_fun_id', $funId)
            ->exists();
        abort_unless($alocado, 403, 'Você não está alocado nesta turma.');
    }

    private function anosLetivosDoProfessor(int $funId): array
    {
        $anos = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $funId)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->pluck('t.tur_anl_id')
            ->unique();

        if ($anos->isEmpty()) {
            return [];
        }

        return AnoLetivo::query()
            ->whereIn('anl_id', $anos->all())
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->toArray();
    }
}
