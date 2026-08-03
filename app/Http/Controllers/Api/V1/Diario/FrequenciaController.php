<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioAula;
use App\Models\Diario\DiarioConteudo;
use App\Models\Diario\DiarioFalta;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\DiaNaoLetivo;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frequência do Diário regular — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\FaltaController, com duas diferenças
 * deliberadas, alinhadas ao PLANO do app:
 *
 *  1. Escopo ESTRITO por lotação: só os tempos do professor logado
 *     (trh_fun_id = user.fun_id). Sem bypass de admin e sem modo supervisão —
 *     quem supervisiona usa o sistema web.
 *  2. Contrato estável e versionado: as chaves desta resposta são consumidas
 *     pelo app Expo e não mudam dentro da v1.
 *
 * As 3 travas de escrita vêm do DiarioEscritaGuard.
 */
class FrequenciaController extends Controller
{
    /** Contexto completo de uma (turma, unidade): tudo que a tela de chamada precisa. */
    public function contexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $uniId = (int) $data['uni_id'];
        $funId = (int) $request->user()->fun_id;

        Guard::assertLotadoNaTurma($funId, $turId);

        // Só os tempos DELE — o app não mostra grade de terceiros.
        $tempos = DB::table('edu_turma_horario as trh')
            ->leftJoin('edu_disciplina as d', 'd.dis_id', '=', 'trh.trh_dis_id')
            ->where('trh.trh_tur_id', $turId)
            ->where('trh.trh_fun_id', $funId)
            ->whereNull('trh.trh_deleted_at')
            ->orderBy('trh.trh_dia')
            ->orderBy('trh.trh_tempo')
            ->get(['trh.trh_id', 'trh.trh_dia', 'trh.trh_tempo', 'trh.trh_hora', 'trh.trh_dis_id', 'd.dis_nome'])
            ->map(fn ($t) => [
                'trh_id'     => (int) $t->trh_id,
                'trh_dia'    => $t->trh_dia,
                'trh_tempo'  => (int) $t->trh_tempo,
                'trh_hora'   => $t->trh_hora,
                'trh_dis_id' => $t->trh_dis_id !== null ? (int) $t->trh_dis_id : null,
                'dis_nome'   => $t->dis_nome,
            ])
            ->values();

        $periodo = Guard::janela($uniId);
        $turmaInfo = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_esc_id', 'tur_anl_id', 'tur_ser_id']);

        // Sábados letivos da escola no período — cada um espelha um dia útil.
        $sabadosLetivos = [];
        if ($periodo && $turmaInfo) {
            $diaRef = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex'];
            $sabadosLetivos = DB::table('cfg_sabado_letivo as s')
                ->where('s.sbl_anl_id', $turmaInfo->tur_anl_id)
                ->whereBetween('s.sbl_dt_sabado', [$periodo['dt_inicio'], $periodo['dt_fim']])
                ->whereNotExists(function ($q) use ($turmaInfo) {
                    $q->select(DB::raw(1))
                        ->from('cfg_sabado_letivo_excecao as e')
                        ->whereColumn('e.sle_sbl_id', 's.sbl_id')
                        ->where('e.sle_esc_id', $turmaInfo->tur_esc_id);
                })
                ->orderBy('s.sbl_dt_sabado')
                ->get(['s.sbl_dt_sabado', 's.sbl_dia_semana'])
                ->map(fn ($r) => [
                    'dt'      => Carbon::parse($r->sbl_dt_sabado)->toDateString(),
                    'dia_ref' => $diaRef[(int) $r->sbl_dia_semana] ?? null,
                ])
                ->filter(fn ($x) => $x['dia_ref'] !== null)
                ->values();
        }

        // Dias não letivos (feriados/recessos) da janela: o app usa para NÃO
        // listar esses dias como pendência na navegação da chamada. Espelha o
        // tratamento dos sábados letivos — a regra do calendário mora no backend.
        $diasNaoLetivos = [];
        if ($periodo && $turmaInfo) {
            $diasNaoLetivos = DiaNaoLetivo::datasNoPeriodo(
                (int) $turmaInfo->tur_anl_id,
                $periodo['dt_inicio'],
                $periodo['dt_fim'],
            );
        }

        // Ativos + quem saiu (para lançar o que é anterior à saída).
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(fn ($s) => $s->whereNotNull('tma_tas_cod_saida')->where('tma_tas_cod_saida', '!=', Matricula::TAS_REMANEJADO));
            })
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => (int) $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'dt_saida'         => $m->tma_dt_saida?->toDateString(),
            ]);

        $trhIds = $tempos->pluck('trh_id');

        $presencas = $trhIds->isEmpty() ? [] : DB::table('edu_diario_aula as a')
            ->join('edu_diario_falta as f', 'f.fal_aul_id', '=', 'a.aul_id')
            ->where('a.aul_tur_id', $turId)
            ->where('a.aul_uni_id', $uniId)
            ->whereIn('a.aul_trh_id', $trhIds)
            ->whereNull('a.aul_deleted_at')
            ->whereNull('f.fal_deleted_at')
            ->get(['a.aul_trh_id', 'a.aul_dt', 'f.fal_aln_id', 'f.fal_fl_presente'])
            ->map(fn ($r) => [
                'trh_id'   => (int) $r->aul_trh_id,
                'dt'       => Carbon::parse($r->aul_dt)->toDateString(),
                'aln_id'   => (int) $r->fal_aln_id,
                'presente' => (bool) $r->fal_fl_presente,
            ])
            ->values();

        $aulasNaoExecutadas = $trhIds->isEmpty() ? [] : DB::table('edu_diario_aula')
            ->where('aul_tur_id', $turId)
            ->where('aul_uni_id', $uniId)
            ->where('aul_fl_nao_executada', true)
            ->whereIn('aul_trh_id', $trhIds)
            ->whereNull('aul_deleted_at')
            ->get(['aul_trh_id', 'aul_dt'])
            ->map(fn ($r) => ['trh_id' => (int) $r->aul_trh_id, 'dt' => Carbon::parse($r->aul_dt)->toDateString()])
            ->values();

        $justificativas = DB::table('edu_justificativa_falta as j')
            ->leftJoin('cfg_motivo_baixa_frequencia as m', 'm.mbf_id', '=', 'j.jfa_mbf_id')
            ->where('j.jfa_tur_id', $turId)
            ->whereNull('j.jfa_deleted_at')
            ->get(['j.jfa_aln_id', 'j.jfa_dt_inicio', 'j.jfa_dt_fim', 'm.mbf_descricao', 'm.mbf_fl_abona'])
            ->map(fn ($r) => [
                'aln_id'    => (int) $r->jfa_aln_id,
                'dt_inicio' => Carbon::parse($r->jfa_dt_inicio)->toDateString(),
                'dt_fim'    => Carbon::parse($r->jfa_dt_fim)->toDateString(),
                'motivo'    => $r->mbf_descricao,
                'abona'     => (bool) $r->mbf_fl_abona,
            ])
            ->values();

        $conteudos = DB::table('edu_diario_conteudo')
            ->where('dco_tur_id', $turId)
            ->where('dco_uni_id', $uniId)
            ->whereNull('dco_deleted_at')
            ->get(['dco_dt', 'dco_dis_id', 'dco_conteudo', 'dco_metodologia', 'dco_fl_plano_executado', 'dco_dpa_id'])
            ->map(fn ($r) => [
                'dt'              => Carbon::parse($r->dco_dt)->toDateString(),
                'dis_id'          => $r->dco_dis_id !== null ? (int) $r->dco_dis_id : null,
                'conteudo'        => $r->dco_conteudo,
                'metodologia'     => $r->dco_metodologia,
                'plano_executado' => (bool) $r->dco_fl_plano_executado,
                'dpa_id'          => $r->dco_dpa_id !== null ? (int) $r->dco_dpa_id : null,
            ])
            ->values();

        // Planos próprios elegíveis (pendente/aprovado) da série — base do "planejamento executado".
        $planos = DB::table('edu_diario_plano_aula')
            ->where('dpa_ser_id', (int) ($turmaInfo->tur_ser_id ?? 0))
            // Turma multi: só o plano DESTA turma ou o que vale para todas (null).
            ->where(fn ($q) => $q->whereNull('dpa_tur_id')->orWhere('dpa_tur_id', $turId))
            ->where('dpa_user_id', (int) $request->user()->id)
            ->whereIn('dpa_status', [DiarioPlanoAula::STATUS_PENDENTE, DiarioPlanoAula::STATUS_APROVADO])
            ->whereNull('dpa_deleted_at')
            ->orderBy('dpa_dt_inicio')
            ->get(['dpa_id', 'dpa_dis_id', 'dpa_dt_inicio', 'dpa_dt_fim', 'dpa_objeto_conhecimento', 'dpa_estrategias'])
            ->map(fn ($p) => [
                'dpa_id'      => (int) $p->dpa_id,
                'dis_id'      => (int) $p->dpa_dis_id,
                'dt_inicio'   => Carbon::parse($p->dpa_dt_inicio)->toDateString(),
                'dt_fim'      => Carbon::parse($p->dpa_dt_fim)->toDateString(),
                'conteudo'    => $p->dpa_objeto_conhecimento,
                'metodologia' => $p->dpa_estrategias,
            ])
            ->values();

        return response()->json([
            'tempos'               => $tempos,
            'alunos'               => $alunos,
            'periodo'              => $periodo,
            'periodo_aberto'       => Guard::periodoAberto($uniId),
            'turma_aberta'         => Guard::turmaAberta($turId),
            'presencas'            => $presencas,
            'conteudos'            => $conteudos,
            'planos'               => $planos,
            'sabados_letivos'      => $sabadosLetivos,
            'dias_nao_letivos'     => $diasNaoLetivos,
            'justificativas'       => $justificativas,
            'aulas_nao_executadas' => $aulasNaoExecutadas,
        ]);
    }

    /**
     * Agenda do professor num dia: os tempos DELE atravessando todas as turmas,
     * em ordem cronológica, com o estado de cada aula.
     *
     * É o que a home "Hoje" precisa para levar o professor direto à chamada sem
     * passar pela cascata ano→escola→turma→disciplina→unidade.
     */
    public function agenda(Request $request): JsonResponse
    {
        $data = $request->validate(['dt' => ['nullable', 'date']]);
        $dt = Carbon::parse($data['dt'] ?? now())->toDateString();
        $funId = (int) $request->user()->fun_id;

        $diaSemana = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
        $diaRef = $diaSemana[Carbon::parse($dt)->dayOfWeek];

        // Sábado letivo espelha os tempos de um dia útil.
        $sabado = DB::table('cfg_sabado_letivo as s')
            ->whereDate('s.sbl_dt_sabado', $dt)
            ->first(['s.sbl_id', 's.sbl_dia_semana', 's.sbl_anl_id']);

        if ($sabado) {
            $diaRef = $diaSemana[(int) $sabado->sbl_dia_semana] ?? $diaRef;
        }

        $tempos = DB::table('edu_turma_horario as trh')
            ->join('edu_turma as t', 't.tur_id', '=', 'trh.trh_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_disciplina as d', 'd.dis_id', '=', 'trh.trh_dis_id')
            ->where('trh.trh_fun_id', $funId)
            ->where('trh.trh_dia', $diaRef)
            ->whereNull('trh.trh_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->orderBy('trh.trh_hora')
            ->orderBy('trh.trh_tempo')
            ->get([
                'trh.trh_id', 'trh.trh_tempo', 'trh.trh_hora', 'trh.trh_dis_id',
                't.tur_id', 't.tur_nome', 't.tur_situacao', 't.tur_anl_id',
                'e.esc_nome', 'd.dis_nome',
            ]);

        // Sábado letivo excluído por exceção da escola não vale para aquela turma.
        if ($sabado) {
            $excecoes = DB::table('cfg_sabado_letivo_excecao')
                ->where('sle_sbl_id', $sabado->sbl_id)
                ->pluck('sle_esc_id')
                ->all();

            $escolaDaTurma = DB::table('edu_turma')
                ->whereIn('tur_id', $tempos->pluck('tur_id')->unique())
                ->pluck('tur_esc_id', 'tur_id');

            $tempos = $tempos
                ->reject(fn ($x) => in_array($escolaDaTurma[$x->tur_id] ?? null, $excecoes))
                ->values();
        }

        // Dia não letivo (feriado/recesso) não gera pendência: os tempos do
        // professor cujo ano marca ESTA data como não letiva saem da agenda. O
        // parâmetro é por ano letivo, então filtra pelo ano de cada turma.
        $anosNaoLetivo = DiaNaoLetivo::anosComDiaNaoLetivo(
            $tempos->pluck('tur_anl_id')->map(fn ($v) => (int) $v)->unique()->all(),
            $dt,
        );
        // Só "é dia não letivo" se havia aula e TODA ela caiu por conta disso —
        // aí o app troca a mensagem vazia genérica pela de feriado.
        $ehDiaNaoLetivo = $tempos->isNotEmpty()
            && $tempos->every(fn ($x) => in_array((int) $x->tur_anl_id, $anosNaoLetivo, true));
        $tempos = $tempos
            ->reject(fn ($x) => in_array((int) $x->tur_anl_id, $anosNaoLetivo, true))
            ->values();

        // Tudo o que o loop precisaria consultar por linha, resolvido de uma vez.
        $turIds = $tempos->pluck('tur_id')->unique();
        $trhIds = $tempos->pluck('trh_id')->unique();

        $unidadesPorAno = DB::table('cfg_unidade')
            ->whereIn('uni_anl_id', $tempos->pluck('tur_anl_id')->unique())
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_anl_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao'])
            ->groupBy('uni_anl_id');

        $aulas = $trhIds->isEmpty() ? collect() : DB::table('edu_diario_aula')
            ->whereIn('aul_tur_id', $turIds)
            ->whereIn('aul_trh_id', $trhIds)
            ->whereDate('aul_dt', $dt)
            ->whereNull('aul_deleted_at')
            ->get(['aul_id', 'aul_tur_id', 'aul_trh_id', 'aul_fl_nao_executada'])
            ->keyBy(fn ($a) => $a->aul_tur_id . '|' . $a->aul_trh_id);

        $contagens = $aulas->isEmpty() ? collect() : DB::table('edu_diario_falta')
            ->whereIn('fal_aul_id', $aulas->pluck('aul_id'))
            ->whereNull('fal_deleted_at')
            ->groupBy('fal_aul_id')
            ->selectRaw('fal_aul_id, SUM(CASE WHEN fal_fl_presente THEN 1 ELSE 0 END) AS p, SUM(CASE WHEN fal_fl_presente THEN 0 ELSE 1 END) AS f')
            ->get()
            ->keyBy('fal_aul_id');

        $totaisAlunos = $turIds->isEmpty() ? collect() : DB::table('edu_turma_aluno')
            ->whereIn('tma_tur_id', $turIds)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->groupBy('tma_tur_id')
            ->selectRaw('tma_tur_id, COUNT(*) AS total')
            ->pluck('total', 'tma_tur_id');

        $hoje = now()->toDateString();

        $itens = $tempos->map(function ($x) use ($dt, $hoje, $unidadesPorAno, $aulas, $contagens, $totaisAlunos) {
            // Unidade que contém a data (com extensão) dentro do ano da turma.
            $uni = ($unidadesPorAno[$x->tur_anl_id] ?? collect())->first(function ($u) use ($dt) {
                $ini = Carbon::parse($u->uni_dt_inicio)->toDateString();
                $fim = Carbon::parse($u->uni_dt_fim)->addDays((int) $u->uni_dias_extensao)->toDateString();
                return $dt >= $ini && $dt <= $fim;
            });

            $turmaAberta = $x->tur_situacao === 'ABERTA';
            // Mesma regra do Guard::periodoAberto, sem ida extra ao banco.
            $periodoAberto = false;
            if ($uni) {
                $ini = Carbon::parse($uni->uni_dt_inicio)->toDateString();
                $fim = Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString();
                $periodoAberto = $hoje >= $ini && $hoje <= $fim;
            }

            $aula = $aulas[$x->tur_id . '|' . $x->trh_id] ?? null;
            $contagem = $aula ? ($contagens[$aula->aul_id] ?? null) : null;
            $presentes = (int) ($contagem->p ?? 0);
            $faltas = (int) ($contagem->f ?? 0);
            $totalAlunos = (int) ($totaisAlunos[$x->tur_id] ?? 0);

            if (! $uni || ! $turmaAberta || ! $periodoAberto) {
                $estado = 'fora_da_janela';
            } elseif ($aula && $aula->aul_fl_nao_executada) {
                $estado = 'nao_executada';
            } elseif ($presentes + $faltas > 0) {
                $estado = 'feita';
            } else {
                $estado = 'pendente';
            }

            return [
                'trh_id'         => (int) $x->trh_id,
                'trh_tempo'      => (int) $x->trh_tempo,
                'trh_hora'       => $x->trh_hora,
                'tur_id'         => (int) $x->tur_id,
                'tur_nome'       => $x->tur_nome,
                // O app precisa do ano para montar o seletor de período na chamada.
                'tur_anl_id'     => (int) $x->tur_anl_id,
                'esc_nome'       => $x->esc_nome,
                'dis_id'         => $x->trh_dis_id !== null ? (int) $x->trh_dis_id : null,
                'dis_nome'       => $x->dis_nome,
                'uni_id'         => $uni ? (int) $uni->uni_id : null,
                'uni_label'      => $uni ? $uni->uni_numero . 'º ' . $uni->uni_tipo : null,
                'turma_aberta'   => $turmaAberta,
                'periodo_aberto' => $periodoAberto,
                'estado'         => $estado,
                'presentes'      => $presentes,
                'faltas'         => $faltas,
                'total_alunos'   => $totalAlunos,
            ];
        })->values();

        return response()->json([
            'dt'               => $dt,
            'dia_ref'          => $diaRef,
            'eh_sabado_letivo' => (bool) $sabado,
            'eh_dia_nao_letivo' => $ehDiaNaoLetivo,
            'tempos'           => $itens,
            'resumo'           => [
                'total'       => $itens->count(),
                'registradas' => $itens->whereIn('estado', ['feita', 'nao_executada'])->count(),
            ],
        ]);
    }

    /** Presença/ausência de 1 aluno em 1 tempo/data (autosave por célula). */
    public function salvarPresenca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'trh_id'   => ['required', 'integer', 'exists:edu_turma_horario,trh_id'],
            'aln_id'   => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $funId = (int) $request->user()->fun_id;
        $slot = Guard::slotDoProfessor($funId, (int) $data['tur_id'], (int) $data['trh_id']);
        Guard::assertTurmaAberta((int) $data['tur_id']);
        Guard::assertPeriodoAberto((int) $data['uni_id']);
        Guard::assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertSemSaidaNaData((int) $data['tur_id'], (int) $data['aln_id'], $data['dt']);

        $aula = $this->resolverAula($request, $data, $slot);

        DiarioFalta::updateOrCreate(
            ['fal_aul_id' => $aula->aul_id, 'fal_aln_id' => $data['aln_id']],
            ['fal_fl_presente' => (bool) $data['presente']],
        );

        return response()->json(['ok' => true]);
    }

    /** Marca todos os alunos elegíveis da turma em 1 tempo/data (lote ✓/✕). */
    public function salvarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'trh_id'   => ['required', 'integer', 'exists:edu_turma_horario,trh_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $funId = (int) $request->user()->fun_id;
        $slot = Guard::slotDoProfessor($funId, (int) $data['tur_id'], (int) $data['trh_id']);
        Guard::assertTurmaAberta((int) $data['tur_id']);
        Guard::assertPeriodoAberto((int) $data['uni_id']);
        Guard::assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        $aula = $this->resolverAula($request, $data, $slot);

        // Ativos + quem ainda não tinha saído na data do lote.
        $alnIds = Matricula::query()
            ->where('tma_tur_id', $data['tur_id'])
            ->whereNull('tma_deleted_at')
            ->where(function ($q) use ($data) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(function ($w) use ($data) {
                        $w->whereNotNull('tma_tas_cod_saida')->whereDate('tma_dt_saida', '>=', $data['dt']);
                    });
            })
            ->pluck('tma_aln_id');

        DB::transaction(function () use ($alnIds, $aula, $data) {
            foreach ($alnIds as $alnId) {
                DiarioFalta::updateOrCreate(
                    ['fal_aul_id' => $aula->aul_id, 'fal_aln_id' => $alnId],
                    ['fal_fl_presente' => (bool) $data['presente']],
                );
            }
        });

        return response()->json(['ok' => true, 'total' => $alnIds->count()]);
    }

    /** Conteúdo/metodologia por (turma, data, disciplina). */
    public function salvarConteudo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'          => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'          => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dis_id'          => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dt'              => ['required', 'date'],
            'conteudo'        => ['nullable', 'string', 'max:5000'],
            'metodologia'     => ['nullable', 'string', 'max:5000'],
            'plano_executado' => ['boolean'],
            'dpa_id'          => ['nullable', 'integer'],
        ]);

        $funId = (int) $request->user()->fun_id;
        Guard::assertLotadoNaTurma($funId, (int) $data['tur_id']);
        Guard::assertDisciplinaDoProfessor($funId, (int) $data['tur_id'], (int) $data['dis_id']);
        Guard::assertTurmaAberta((int) $data['tur_id']);
        Guard::assertPeriodoAberto((int) $data['uni_id']);
        Guard::assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        $executado   = (bool) ($data['plano_executado'] ?? false);
        $conteudo    = $data['conteudo'] ?? null;
        $metodologia = $data['metodologia'] ?? null;
        $dpaId       = null;

        if ($executado) {
            // Travado: o texto vem do plano (snapshot), não do cliente.
            $plano       = $this->planoExecutavel($request, (int) $data['tur_id'], (int) $data['dis_id'], $data['dt'], $data['dpa_id'] ?? null);
            $dpaId       = (int) $plano->dpa_id;
            $conteudo    = $plano->dpa_objeto_conhecimento;
            $metodologia = $plano->dpa_estrategias;
        }

        DiarioConteudo::updateOrCreate(
            ['dco_tur_id' => $data['tur_id'], 'dco_dt' => $data['dt'], 'dco_dis_id' => $data['dis_id']],
            [
                'dco_user_id'            => (int) $request->user()->id,
                'dco_uni_id'             => $data['uni_id'],
                'dco_conteudo'           => $conteudo,
                'dco_metodologia'        => $metodologia,
                'dco_fl_plano_executado' => $executado,
                'dco_dpa_id'             => $dpaId,
            ],
        );

        return response()->json([
            'ok'              => true,
            'plano_executado' => $executado,
            'dpa_id'          => $dpaId,
            'conteudo'        => $conteudo,
            'metodologia'     => $metodologia,
        ]);
    }

    /** Marca/desmarca a aula (tempo + data) como NÃO executada. */
    public function salvarAulaNaoExecutada(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'        => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'        => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'trh_id'        => ['required', 'integer', 'exists:edu_turma_horario,trh_id'],
            'dt'            => ['required', 'date'],
            'nao_executado' => ['required', 'boolean'],
        ]);

        $funId = (int) $request->user()->fun_id;
        $slot = Guard::slotDoProfessor($funId, (int) $data['tur_id'], (int) $data['trh_id']);
        Guard::assertTurmaAberta((int) $data['tur_id']);
        Guard::assertPeriodoAberto((int) $data['uni_id']);
        Guard::assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        if (! (bool) $data['nao_executado']) {
            // Desmarca: sem frequência lançada a aula some; com frequência, só limpa a flag.
            $aula = DiarioAula::where('aul_tur_id', $data['tur_id'])
                ->where('aul_trh_id', $data['trh_id'])
                ->whereDate('aul_dt', Carbon::parse($data['dt'])->toDateString())
                ->first();

            if ($aula) {
                $temFalta = DB::table('edu_diario_falta')
                    ->where('fal_aul_id', $aula->aul_id)
                    ->whereNull('fal_deleted_at')
                    ->exists();

                $temFalta ? $aula->update(['aul_fl_nao_executada' => false]) : $aula->delete();
            }

            return response()->json(['ok' => true, 'nao_executado' => false]);
        }

        $aula = $this->resolverAula($request, $data, $slot);
        $aula->update(['aul_fl_nao_executada' => true]);

        return response()->json(['ok' => true, 'nao_executado' => true]);
    }

    // ============ Helpers ============

    /** Cria/recupera a aula (turma, tempo, data). */
    private function resolverAula(Request $request, array $data, object $slot): DiarioAula
    {
        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);

        $aula = DiarioAula::updateOrCreate(
            [
                'aul_tur_id' => $data['tur_id'],
                'aul_trh_id' => $data['trh_id'],
                'aul_dt'     => $data['dt'],
            ],
            [
                'aul_user_id' => (int) $request->user()->id,
                'aul_esc_id'  => $turma->tur_esc_id,
                'aul_anl_id'  => $turma->tur_anl_id,
                'aul_uni_id'  => $data['uni_id'],
                'aul_dis_id'  => $slot->trh_dis_id,
            ],
        );

        // Autoria só no primeiro lançamento — se a grade mudar, a aula segue de quem a deu.
        if ($aula->aul_fun_id === null) {
            $aula->update(['aul_fun_id' => $slot->trh_fun_id]);
        }

        return $aula;
    }

    /** Plano próprio, elegível e que cobre a data, para a disciplina. */
    private function planoExecutavel(Request $request, int $turId, int $disId, string $dt, ?int $dpaId): object
    {
        $d = Carbon::parse($dt)->toDateString();
        $serId = (int) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');

        $plano = DB::table('edu_diario_plano_aula')
            ->where('dpa_ser_id', $serId)
            // Mesma regra da listagem: plano desta turma ou sem turma definida.
            ->where(fn ($q) => $q->whereNull('dpa_tur_id')->orWhere('dpa_tur_id', $turId))
            ->where('dpa_dis_id', $disId)
            ->where('dpa_user_id', (int) $request->user()->id)
            ->whereIn('dpa_status', [DiarioPlanoAula::STATUS_PENDENTE, DiarioPlanoAula::STATUS_APROVADO])
            ->whereDate('dpa_dt_inicio', '<=', $d)
            ->whereDate('dpa_dt_fim', '>=', $d)
            ->whereNull('dpa_deleted_at')
            ->when($dpaId, fn ($q) => $q->where('dpa_id', $dpaId))
            ->orderBy('dpa_dt_inicio')
            ->first(['dpa_id', 'dpa_objeto_conhecimento', 'dpa_estrategias']);

        abort_unless($plano, 422, 'Não há planejamento (pendente/aprovado) para esta disciplina nesta data.');

        return $plano;
    }
}
