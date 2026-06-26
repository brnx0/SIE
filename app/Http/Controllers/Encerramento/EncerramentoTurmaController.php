<?php

namespace App\Http\Controllers\Encerramento;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Support\CalculoEncerramento;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Encerramento de Turmas — POR ENQUANTO SOMENTE CONSULTA (sem insert/update).
 * Lista as turmas de uma escola e, em cada turma, os alunos com a situação da
 * matrícula e se todas as notas estão lançadas (todas as disciplinas × unidades).
 */
class EncerramentoTurmaController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('encerramento/turmas/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Dados do encerramento: turmas da escola + alunos com situação e completude de notas. */
    public function dados(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);

        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo']);
        $uniIds   = $unidades->pluck('uni_id')->map(fn ($v) => (int) $v)->all();
        $uniLabel = $unidades->mapWithKeys(fn ($u) => [(int) $u->uni_id => $u->uni_numero.'º '.ucfirst($u->uni_tipo)])->all();

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('seg.seg_ordem')
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 't.tur_ser_id', 's.ser_nome', 's.seg_id', 'seg.seg_nome_reduzido', DB::raw('s.ser_tipo_avaliacao::text as ser_tipo_avaliacao')]);

        // Segmentos presentes nas turmas da escola (p/ o filtro). Só os que têm turma.
        $segmentos = $turmas
            ->filter(fn ($t) => $t->seg_id !== null)
            ->unique('seg_id')
            ->sortBy('seg_nome_reduzido')
            ->map(fn ($t) => ['seg_id' => (int) $t->seg_id, 'seg_nome' => $t->seg_nome_reduzido])
            ->values();

        $linhas = $turmas->map(function ($t) use ($uniIds, $uniLabel) {
            $tipos = json_decode((string) $t->ser_tipo_avaliacao, true) ?: [];
            $tipos = is_array($tipos) ? $tipos : [];
            // Avaliativa (notas numérica/conceitual) tem precedência: se a série lança notas,
            // a completude é por NOTA. Diagnóstico só vale quando a série NÃO é avaliativa.
            $avaliativa  = (bool) array_intersect(['numerica', 'conceitual'], $tipos);
            $diagnostico = ! $avaliativa && in_array('diagnostico', $tipos, true);

            // Disciplinas da grade da série.
            $disciplinas = DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $t->tur_ser_id)
                ->where('gd.grd_fl_ativo', true)
                ->orderBy('gd.grd_ordem')
                ->orderBy('d.dis_nome')
                ->get(['d.dis_id', 'd.dis_nome']);
            $disIds   = $disciplinas->pluck('dis_id')->map(fn ($v) => (int) $v)->all();
            $disNome  = $disciplinas->mapWithKeys(fn ($d) => [(int) $d->dis_id => $d->dis_nome])->all();

            // ===== Diagnóstico: indicadores (por disciplina) × unidades preenchidos =====
            $indPorDis = [];
            $dgnSet = [];
            if ($diagnostico && $disIds && $uniIds) {
                foreach (DB::table('edu_serie_indicador')
                    ->where('ind_ser_id', $t->tur_ser_id)
                    ->whereIn('ind_dis_id', $disIds)
                    ->where('ind_fl_ativo', true)
                    ->whereNull('ind_deleted_at')
                    ->get(['ind_id', 'ind_dis_id']) as $ind) {
                    $indPorDis[(int) $ind->ind_dis_id][] = (int) $ind->ind_id;
                }
                foreach (\App\Models\Diario\DiarioDiagnostico::where('dgn_tur_id', $t->tur_id)
                    ->get(['dgn_dis_id', 'dgn_uni_id', 'dgn_aln_id', 'dgn_ind_id']) as $g) {
                    $dgnSet[(int) $g->dgn_dis_id][(int) $g->dgn_uni_id][(int) $g->dgn_aln_id][(int) $g->dgn_ind_id] = true;
                }
            }

            // Células de nota lançadas: manual (precedência) + calculado completo.
            $manualSet = [];
            if ($avaliativa && $disIds && $uniIds) {
                foreach (DB::table('edu_nota_manual')
                    ->where('nmn_tur_id', $t->tur_id)
                    ->whereNull('nmn_deleted_at')
                    ->where(fn ($q) => $q->whereNotNull('nmn_media')->orWhereNotNull('nmn_cnc_id'))
                    ->get(['nmn_dis_id', 'nmn_uni_id', 'nmn_aln_id']) as $m) {
                    $manualSet[(int) $m->nmn_dis_id][(int) $m->nmn_uni_id][(int) $m->nmn_aln_id] = true;
                }
            }
            $calc = ($avaliativa && $disIds && $uniIds)
                ? CalculoNota::calculadoTurma((int) $t->tur_id, $disIds, $uniIds) // [dis][aln][uni]
                : [];

            $lancada = fn (int $dis, int $uni, int $aln): bool => isset($manualSet[$dis][$uni][$aln]) || isset($calc[$dis][$aln][$uni]);

            // Elegível ao conselho = aluno que NÃO seria aprovado (reprova em nota ou frequência).
            // Calcula a prévia da situação (sem conselho); o front só libera o toggle para esses.
            $reprovaria = [];
            if ($avaliativa) {
                foreach (CalculoEncerramento::calcular((int) $t->tur_id, [])['resultados'] as $aln => $r) {
                    $reprovaria[(int) $aln] = $r['reprovou_nota'] || $r['reprovou_falta'];
                }
            }

            // Alunos ativos da turma.
            $alunos = Matricula::query()
                ->where('tma_tur_id', $t->tur_id)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with(['aluno:aln_id,aln_nome,aln_nr_matricula', 'situacaoEntrada:tas_cod,tas_descricao,tas_descricao_enturmacao', 'situacaoSaida:tas_cod,tas_descricao,tas_descricao_enturmacao'])
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->unique(fn ($m) => $m->aluno->aln_id)
                ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
                ->values()
                ->map(function (Matricula $m) use ($diagnostico, $avaliativa, $disIds, $uniIds, $disNome, $uniLabel, $lancada, $indPorDis, $dgnSet, $reprovaria) {
                    $alnId = (int) $m->aluno->aln_id;

                    $pendencias = [];
                    $totalCelulas = 0;
                    $faltamTotal = 0;

                    if ($diagnostico) {
                        // Diagnóstico: cada (disciplina × indicador × unidade) deve ter opção lançada.
                        foreach ($disIds as $dis) {
                            $inds = $indPorDis[$dis] ?? [];
                            foreach ($uniIds as $uni) {
                                $faltam = 0;
                                foreach ($inds as $ind) {
                                    $totalCelulas++;
                                    if (! isset($dgnSet[$dis][$uni][$alnId][$ind])) {
                                        $faltam++;
                                    }
                                }
                                $faltamTotal += $faltam;
                                if ($faltam > 0) {
                                    $pendencias[] = ['disciplina' => $disNome[$dis] ?? '—', 'unidade' => ($uniLabel[$uni] ?? '—')." ({$faltam} indicador(es))"];
                                }
                            }
                        }
                        $status = $totalCelulas === 0 ? 'sem_grade' : ($faltamTotal === 0 ? 'completo' : 'pendente');
                    } elseif ($avaliativa) {
                        foreach ($disIds as $dis) {
                            foreach ($uniIds as $uni) {
                                $totalCelulas++;
                                if (! $lancada($dis, $uni, $alnId)) {
                                    $faltamTotal++;
                                    $pendencias[] = ['disciplina' => $disNome[$dis] ?? '—', 'unidade' => $uniLabel[$uni] ?? '—'];
                                }
                            }
                        }
                        $status = $totalCelulas === 0 ? 'sem_grade' : ($faltamTotal === 0 ? 'completo' : 'pendente');
                    } else {
                        $status = 'nao_avaliativa';
                    }

                    $encerrado = $m->tma_dt_encerramento !== null;

                    return [
                        'aln_id'            => $alnId,
                        'nome'             => $m->aluno->aln_nome,
                        'matricula'        => $m->aluno->aln_nr_matricula,
                        'situacao'         => $this->situacaoLabel($m),
                        'status'           => $status,
                        'total'            => $totalCelulas,
                        'lancadas'         => max(0, $totalCelulas - $faltamTotal),
                        'pendencias'       => $pendencias,
                        'encerrado'        => $encerrado,
                        'situacao_final'   => $encerrado ? $this->situacaoLabel($m) : null,
                        // Conselho só p/ quem está completo E não atingiria média/frequência.
                        'elegivel_conselho' => $status === 'completo' && ($reprovaria[$alnId] ?? false),
                    ];
                });

            // Turma encerrada = todos os alunos ativos têm situação final gravada.
            $encerrada = $alunos->isNotEmpty() && $alunos->every(fn ($a) => $a['encerrado']);

            return [
                'tur_id'      => (int) $t->tur_id,
                'turma'       => $t->tur_nome,
                'serie'       => $t->ser_nome,
                'seg_id'      => $t->seg_id !== null ? (int) $t->seg_id : null,
                'segmento'    => $t->seg_nome_reduzido,
                'avaliativa'  => $avaliativa,
                'encerrada'   => $encerrada,
                'alunos'      => $alunos,
            ];
        })->values();

        return response()->json([
            'unidades'  => $unidades->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $uniLabel[(int) $u->uni_id]]),
            'segmentos' => $segmentos,
            'turmas'    => $linhas,
        ]);
    }

    /** Encerra a turma: calcula e grava a situação final de cada aluno ATIVO. */
    public function encerrar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'     => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'conselho'   => ['array'],
            'conselho.*' => ['integer'],
        ]);

        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->whereNull('tur_deleted_at')->first(['tur_id', 'tur_esc_id']);
        abort_unless($turma, 404);
        $this->autorizarEscola((int) $turma->tur_esc_id);

        $calc = CalculoEncerramento::calcular((int) $data['tur_id'], array_map('intval', $data['conselho'] ?? []));

        if (! empty($calc['erros'])) {
            $nomes = DB::table('edu_aluno')->whereIn('aln_id', array_keys($calc['erros']))->pluck('aln_nome', 'aln_id');
            $msgs = [];
            foreach ($calc['erros'] as $aln => $msg) {
                $msgs[] = ($nomes[$aln] ?? "Aluno {$aln}").': '.$msg;
            }

            return response()->json(['ok' => false, 'message' => 'Não foi possível encerrar.', 'erros' => $msgs], 422);
        }

        if (empty($calc['resultados'])) {
            return response()->json(['ok' => false, 'message' => 'Nenhum aluno ativo para encerrar.'], 422);
        }

        $agora = Carbon::now();
        DB::transaction(function () use ($calc, $data, $agora) {
            foreach ($calc['resultados'] as $aln => $r) {
                Matricula::where('tma_tur_id', $data['tur_id'])
                    ->where('tma_aln_id', $aln)
                    ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->whereNull('tma_deleted_at')
                    ->update(['tma_tas_cod_saida' => $r['tas_cod'], 'tma_dt_encerramento' => $agora]);
            }
            DB::table('edu_turma')->where('tur_id', $data['tur_id'])->update(['tur_situacao' => 'ENCERRADA']);
        });

        return response()->json(['ok' => true]);
    }

    /** Cancela o encerramento da turma inteira (limpa a situação final de todos) e reabre. */
    public function cancelar(Request $request): JsonResponse
    {
        $data = $request->validate(['tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id']]);
        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id']);
        abort_unless($turma, 404);
        $this->autorizarEscola((int) $turma->tur_esc_id);

        DB::transaction(function () use ($data) {
            Matricula::where('tma_tur_id', $data['tur_id'])
                ->whereNotNull('tma_dt_encerramento')
                ->update(['tma_tas_cod_saida' => null, 'tma_dt_encerramento' => null]);
            DB::table('edu_turma')->where('tur_id', $data['tur_id'])->update(['tur_situacao' => 'ABERTA']);
        });

        return response()->json(['ok' => true]);
    }

    /** Cancela o encerramento de 1 aluno (limpa só a situação final dele) e reabre a turma. */
    public function cancelarAluno(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
        ]);
        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id']);
        abort_unless($turma, 404);
        $this->autorizarEscola((int) $turma->tur_esc_id);

        DB::transaction(function () use ($data) {
            Matricula::where('tma_tur_id', $data['tur_id'])
                ->where('tma_aln_id', $data['aln_id'])
                ->whereNotNull('tma_dt_encerramento')
                ->update(['tma_tas_cod_saida' => null, 'tma_dt_encerramento' => null]);
            DB::table('edu_turma')->where('tur_id', $data['tur_id'])->update(['tur_situacao' => 'ABERTA']);
        });

        return response()->json(['ok' => true]);
    }

    /** Situação exibida: encerrado → situação final (saída); ativo → entrada; demais → saída. Descrição de enturmação. */
    private function situacaoLabel(Matricula $m): string
    {
        if ($m->tma_dt_encerramento !== null && $m->situacaoSaida) {
            $s = $m->situacaoSaida;

            return $s->tas_descricao_enturmacao ?: $s->tas_descricao;
        }

        if ($m->tma_situacao === Matricula::SITUACAO_ATIVA) {
            $s = $m->situacaoEntrada;

            return $s?->tas_descricao_enturmacao ?: ($s?->tas_descricao ?? 'Matriculado(a)');
        }

        $s = $m->situacaoSaida;

        return $s?->tas_descricao_enturmacao ?: ($s?->tas_descricao ?? (string) $m->tma_situacao);
    }

    /** Admin vê qualquer escola; secretaria só as da sua lotação. */
    private function autorizarEscola(int $escId): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $ids = UserAccess::escolasIds($user);
        abort_unless(is_array($ids) && in_array($escId, $ids, true), 403, 'Você não tem acesso a esta escola.');
    }
}
