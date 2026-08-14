<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Matricula\Matricula;
use App\Support\CalculoNota;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use App\Support\Diario\FrequenciaMensal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consultas somente-leitura do professor — API v1.
 *
 * Nada aqui recalcula regra: a média/M.F vem de CalculoNota::resultado e a
 * completude vem de CalculoNota::calculadoTurma, que são a fonte única do
 * sistema (usadas por boletim, ata final e encerramento).
 *
 * Armadilhas do domínio que este controller respeita:
 *  · a coluna "Média" do mapa é a SOMA das notas regulares, não a média
 *    aritmética — as avaliações dividem um teto de 10;
 *  · a M.F NÃO é a soma: ela embute recuperação da unidade e o override manual
 *    da secretaria, então tem que vir do CalculoNota;
 *  · `calculadoTurma` só devolve a célula quando está COMPLETA — a ausência é
 *    que indica pendência;
 *  · unidades que ainda não começaram não são pendência (senão o 4º bimestre
 *    apareceria vermelho em março).
 */
class ConsultasController extends Controller
{
    /** Mapa de notas: alunos × avaliações de (turma, disciplina, unidade). */
    public function mapaNotas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);

        $hoje = now()->toDateString();

        // Avaliações FUTURAS ficam fora do mapa inteiro (regra do relatório web).
        $avaliacoes = DiarioAvaliacao::query()
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->orderBy('ava_dt')
            ->orderBy('ava_id')
            ->get(['ava_id', 'ava_iav_id', 'ava_tipo', 'ava_descricao', 'ava_dt', 'ava_valor', 'ava_fl_recuperacao'])
            ->reject(fn ($a) => $a->ava_dt && Carbon::parse($a->ava_dt)->toDateString() > $hoje)
            ->values();

        $regulares = $avaliacoes->where('ava_fl_recuperacao', false)->values();
        $recuperacoes = $avaliacoes->where('ava_fl_recuperacao', true)->values();

        $nomes = InstrumentoAvaliativo::pluck('iav_nome', 'iav_id');

        // Unifica avaliações pelo INSTRUMENTO (ava_iav_id): o mapa não exibe data
        // nem descrição, então a mesma "observação" que existe em numérica E
        // conceitual (ou em datas diferentes) deve ocupar UMA coluna só — a nota do
        // aluno vem do tipo/lançamento em que ele foi avaliado. Avaliação sem
        // instrumento fica numa chave única. $regulares já vem ordenado por ava_dt/ava_id.
        $chaveGrupo = fn ($a) => $a->ava_iav_id !== null ? 'iav:' . $a->ava_iav_id : 'ava:' . $a->ava_id;
        $grupos = $regulares->groupBy($chaveGrupo)->values();

        // Representante do grupo (o mesmo usado no cabeçalho e nas células, para
        // casar por ava_id): o que tem valor (numérica/faixa) ou o primeiro.
        $repDoGrupo = fn ($grupo) => $grupo->first(fn ($a) => $a->ava_valor !== null) ?? $grupo->first();

        $colunas = $grupos->map(function ($grupo, $i) use ($nomes, $repDoGrupo) {
            $a = $repDoGrupo($grupo);
            $instrumento = $nomes[$a->ava_iav_id] ?? null;
            $desc = $a->ava_descricao;
            // Mesmo rótulo do relatório: "Instrumento (descrição)" quando diferem.
            $rotulo = $instrumento && $desc && mb_strtolower($instrumento) !== mb_strtolower($desc)
                ? "{$instrumento} ({$desc})"
                : ($instrumento ?: ($desc ?: 'Aval. ' . ($i + 1)));

            return [
                'ava_id'    => (int) $a->ava_id,
                'rotulo'    => $rotulo,
                'ava_valor' => $a->ava_valor !== null ? (float) $a->ava_valor : null,
                'ava_dt'    => $a->ava_dt ? Carbon::parse($a->ava_dt)->toDateString() : null,
                'ava_tipo'  => $a->ava_tipo,
            ];
        });

        $notas = DiarioNota::whereIn('nta_ava_id', $avaliacoes->pluck('ava_id'))
            ->get(['nta_ava_id', 'nta_aln_id', 'nta_valor', 'nta_cnc_id'])
            ->groupBy('nta_aln_id');

        $siglas = DB::table('cfg_conceito')->pluck('cnc_sigla', 'cnc_id');

        // Fonte única da M.F — já com recuperação e override manual aplicados.
        $resultados = CalculoNota::resultado($turId, $disId, $uniId);

        $linhas = $this->alunosDaTurma($turId)->map(function ($m) use ($regulares, $grupos, $repDoGrupo, $recuperacoes, $notas, $siglas, $resultados) {
            $alnId = (int) $m->aluno->aln_id;
            $doAluno = $notas->get($alnId);

            // Uma célula por GRUPO (coluna). O `ava_id` é o do REPRESENTANTE (igual
            // ao cabeçalho, para o app casar coluna×célula); o TEXTO vem da nota que
            // o aluno tem no grupo — no tipo em que ele foi avaliado.
            $celulas = $grupos->map(function ($grupo) use ($doAluno, $siglas, $repDoGrupo) {
                $rep = $repDoGrupo($grupo);
                $n = null;
                $avaComNota = $rep;
                foreach ($grupo as $a) {
                    $achou = $doAluno?->firstWhere('nta_ava_id', $a->ava_id);
                    if ($achou) {
                        $avaComNota = $a;
                        $n = $achou;
                        break;
                    }
                }
                return [
                    'ava_id' => (int) $rep->ava_id,
                    'texto'  => $this->textoNota($avaComNota, $n, $siglas),
                    'valor'  => $n && $n->nta_valor !== null ? (float) $n->nta_valor : null,
                ];
            })->values();

            // TOTAL = SOMA das notas regulares (as avaliações dividem um teto de
            // 10) — não é média aritmética.
            //
            // Soma tudo que estiver gravado em nta_valor: isso cobre a numérica E
            // a conceitual em modo "faixa", onde o total é justamente o que
            // determina o conceito. No modo "conceito" não há valor, e o total
            // fica nulo — correto, ali a M.F sai da moda dos conceitos.
            $valores = $regulares
                ->map(fn ($a) => $doAluno?->firstWhere('nta_ava_id', $a->ava_id)?->nta_valor)
                ->filter(fn ($v) => $v !== null);
            $soma = $valores->isEmpty() ? null : round((float) $valores->sum(), 2);

            // Recuperação exibida = a MAIOR entre as de recuperação do contexto.
            $rec = $recuperacoes
                ->map(fn ($a) => $doAluno?->firstWhere('nta_ava_id', $a->ava_id)?->nta_valor)
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (float) $v)
                ->max();

            $r = $resultados[$alnId] ?? null;

            return [
                'aln_id'    => $alnId,
                'aln_nome'  => $m->aluno->aln_nome,
                'celulas'   => $celulas,
                'soma'      => $soma,
                'recuperacao' => $rec,
                // M.F pronta do backend — o app NÃO deve derivar da soma.
                'mf'        => [
                    'texto'    => $this->textoResultado($r),
                    'valor'    => $r['valor'] ?? null,
                    'sigla'    => $r['conceito']['sigla'] ?? null,
                    'peso'     => $r['conceito']['peso'] ?? null,
                    'completo' => (bool) ($r['completo'] ?? false),
                ],
            ];
        })->values();

        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first();
        $turma = DB::table('edu_turma as t')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->first(['t.tur_nome', 'e.esc_nome', 's.ser_nome']);

        return response()->json([
            'contexto' => [
                'tur_nome'       => $turma->tur_nome ?? null,
                'ser_nome'       => $turma->ser_nome ?? null,
                'esc_nome'       => $turma->esc_nome ?? null,
                'dis_nome'       => DB::table('edu_disciplina')->where('dis_id', $disId)->value('dis_nome'),
                'uni_label'      => $uni ? $uni->uni_numero . 'º ' . $uni->uni_tipo : null,
                'periodo_aberto' => Guard::periodoAberto($uniId),
                'turma_aberta'   => Guard::turmaAberta($turId),
            ],
            'colunas'         => $colunas,
            'tem_recuperacao' => $recuperacoes->isNotEmpty(),
            'linhas'          => $linhas,
        ]);
    }

    /**
     * "Minhas pendências": o que falta lançar nas turmas que o professor rege.
     * Escopo por REGÊNCIA (edu_turma_professor), igual ao resto do contexto.
     */
    public function pendencias(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id' => ['nullable', 'integer'],
            // Professor lotado em mais de uma escola precisa fechar o diário de
            // uma por vez. O filtro é aqui e não no app porque o RESUMO
            // (células pendentes, lançamentos faltando, turmas) é somado no
            // servidor — filtrar só a lista deixaria o resumo mentindo.
            'esc_id' => ['nullable', 'integer'],
        ]);

        $anlId = (int) $data['anl_id'];
        $funId = (int) $request->user()->fun_id;
        abort_unless($funId, 422, 'Seu usuário não possui vínculo de funcionário.');

        // Turmas × disciplinas que ELE rege neste ano.
        $regencias = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('t.tur_anl_id', $anlId)
            // Mesma modalidade que o ContextoDiarioService usa em "minhas turmas".
            // Sem isto, turmas de AEE/Atividade apareceriam aqui e não lá.
            ->where('t.tur_modalidade', 'REGULAR')
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->when($data['tur_id'] ?? null, fn ($q, $v) => $q->where('t.tur_id', $v))
            ->when($data['esc_id'] ?? null, fn ($q, $v) => $q->where('t.tur_esc_id', $v))
            // Escola primeiro na ordenação: agrupa visualmente quem tem turmas
            // em mais de uma, mesmo com o filtro em "todas".
            ->orderBy('e.esc_nome')
            ->orderBy('t.tur_nome')
            ->orderBy('d.dis_nome')
            ->get(['t.tur_id', 't.tur_nome', 't.tur_situacao', 't.tur_esc_id', 'd.dis_id', 'd.dis_nome', 'e.esc_nome']);

        // Só períodos JÁ INICIADOS contam como pendência — o 4º bimestre não
        // pode aparecer vermelho em março.
        $hoje = now()->toDateString();
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao'])
            ->filter(fn ($u) => Carbon::parse($u->uni_dt_inicio)->toDateString() <= $hoje)
            ->values();

        $uniIds = $unidades->pluck('uni_id')->map(fn ($v) => (int) $v)->all();

        $itens = collect();

        foreach ($regencias->groupBy('tur_id') as $turId => $doTurma) {
            if (! $uniIds) {
                continue;
            }

            $disIds = $doTurma->pluck('dis_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
            $totalAlunos = count(CalculoNota::alunos((int) $turId));

            // [dis][aln][uni] e SÓ com as células completas — a ausência é a pendência.
            $calc = CalculoNota::calculadoTurma((int) $turId, $disIds, $uniIds);

            foreach ($doTurma as $r) {
                foreach ($unidades as $u) {
                    $completos = 0;
                    foreach (($calc[(int) $r->dis_id] ?? []) as $porUni) {
                        if (isset($porUni[(int) $u->uni_id])) {
                            $completos++;
                        }
                    }

                    $faltando = max(0, $totalAlunos - $completos);
                    if ($faltando === 0) {
                        continue;
                    }

                    $janelaAberta = Guard::periodoAberto((int) $u->uni_id);

                    $itens->push([
                        'tur_id'         => (int) $r->tur_id,
                        'tur_nome'       => $r->tur_nome,
                        'esc_id'         => (int) $r->tur_esc_id,
                        'esc_nome'       => $r->esc_nome,
                        'dis_id'         => (int) $r->dis_id,
                        'dis_nome'       => $r->dis_nome,
                        'uni_id'         => (int) $u->uni_id,
                        'uni_label'      => $u->uni_numero . 'º ' . $u->uni_tipo,
                        'total_alunos'   => $totalAlunos,
                        'completos'      => $completos,
                        'faltando'       => $faltando,
                        'periodo_aberto' => $janelaAberta,
                        'turma_aberta'   => $r->tur_situacao === 'ABERTA',
                    ]);
                }
            }
        }

        return response()->json([
            'anl_id'   => $anlId,
            'resumo'   => [
                'celulas_pendentes' => $itens->count(),
                'lancamentos_faltando' => (int) $itens->sum('faltando'),
                'turmas' => $itens->pluck('tur_id')->unique()->count(),
            ],
            'pendencias' => $itens->values(),
        ]);
    }

    /**
     * Frequência do mês por aluno. O cálculo vem de App\Support\Diario\FrequenciaMensal,
     * a MESMA classe que o relatório web usa — não há fórmula duplicada aqui.
     */
    public function frequencia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'mes'    => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $turId = (int) $data['tur_id'];
        $mes = (int) $data['mes'];

        Guard::assertRegenciaTurma((int) $request->user()->fun_id, $turId);

        $turma = DB::table('edu_turma as t')
            ->join('cfg_ano_letivo as a', 'a.anl_id', '=', 't.tur_anl_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('t.tur_id', $turId)
            ->first(['t.tur_nome', 't.tur_anl_id', 'a.anl_ano', 'e.esc_nome']);

        $r = FrequenciaMensal::calcular($turId, (int) $turma->tur_anl_id, (int) $turma->anl_ano, $mes);

        return response()->json([
            'contexto' => [
                'tur_nome' => $turma->tur_nome,
                'esc_nome' => $turma->esc_nome,
                'anl_ano'  => (int) $turma->anl_ano,
                'mes'      => $mes,
            ],
            // A base difere por aluno numa multi-seriada — por isso os dois vêm,
            // e cada aluno diz qual modo usou.
            'total_aulas'  => $r['total_aulas'],
            'dias_letivos' => $r['dias_letivos'],
            'modos'        => $r['modos'],
            'alunos'       => $r['alunos'],
        ]);
    }

    // ============ Helpers ============

    private function textoNota($avaliacao, $nota, $siglas): string
    {
        if (! $nota) {
            return '—';
        }

        if ($avaliacao->ava_tipo === DiarioAvaliacao::TIPO_NUMERICA) {
            return $nota->nta_valor !== null ? number_format((float) $nota->nta_valor, 2, ',', '.') : '—';
        }

        // Conceitual tem DOIS armazenamentos: no modo 'conceito' vai em
        // nta_cnc_id; no modo 'faixa' vai em nta_valor. Ler só o cnc_id (como o
        // relatório web faz) deixa a tabela inteira com "—" numa série faixa,
        // enquanto a M.F mostra a sigla — incoerente para quem lê.
        if ($nota->nta_cnc_id !== null) {
            return $siglas[$nota->nta_cnc_id] ?? '—';
        }

        return $nota->nta_valor !== null ? number_format((float) $nota->nta_valor, 2, ',', '.') : '—';
    }

    private function textoResultado(?array $r): string
    {
        if (! $r) {
            return '—';
        }
        if (! empty($r['conceito']['sigla'])) {
            return $r['conceito']['sigla'];
        }

        return $r['valor'] !== null ? number_format((float) $r['valor'], 2, ',', '.') : '—';
    }

    /**
     * Roster do mapa: ativos + quem saiu (menos remanejados) — o mesmo do
     * diário. Atenção: CalculoNota::resultado só cobre ATIVOS, então quem saiu
     * aparece na linha com M.F "—", e isso é correto.
     */
    private function alunosDaTurma(int $turId)
    {
        return Matricula::query()
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
            ->values();
    }
}
