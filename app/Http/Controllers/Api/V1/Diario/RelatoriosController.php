<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Turma\Turma;
use App\Support\CalculoNota;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use App\Support\FaltasAluno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Relatórios somente-leitura do professor — API v1.
 *
 * Espelha os relatórios web `Relatorio\NotasPorUnidadeController` e
 * `Relatorio\BoletimAvaliativoController`, com duas diferenças deliberadas:
 *
 *  1. devolve JSON em vez de Inertia, e SEM o papel timbrado (ParametroEntidade,
 *     logomarca, brasão, endereço da escola, msg_cab_*) — o app não imprime;
 *  2. a autorização é só a do professor: regência em edu_turma_professor. Os
 *     ramos de admin e de secretaria (acessoPorEscola) do `autorizarTurma()` do
 *     web NÃO existem aqui — é a política já vigente em toda a API v1.
 *
 * O CÁLCULO não é reimplementado: vem de CalculoNota (resultado / consolidar /
 * recuperacoesFinais) e de FaltasAluno::totaisDisciplina, exatamente as classes
 * que o web chama. Qualquer divergência de número entre app e web é bug aqui,
 * não regra nova.
 *
 * Armadilhas do domínio que este controller respeita:
 *  · o roster de cada relatório é DIFERENTE — "notas por unidade" é só matrícula
 *    ATIVA; o boletim inclui quem saiu (menos remanejado) e NÃO deduplica por
 *    aluno, porque duas matrículas na mesma turma são duas linhas no modelo;
 *  · no boletim, o "Resultado Final" vem do ENCERRAMENTO (tma_dt_encerramento),
 *    nunca de recalcular por média — ver comentário em `linhaBoletim()`;
 *  · a M.F embute recuperação de unidade, recuperação FINAL e o override manual
 *    da secretaria; por isso passa por CalculoNota::consolidar e não por soma.
 */
class RelatoriosController extends Controller
{
    /** Turnos por código, como no relatório web. */
    private const TURNOS = ['M' => 'Matutino', 'T' => 'Vespertino', 'V' => 'Vespertino', 'N' => 'Noturno', 'INTEGRAL' => 'Integral'];

    /**
     * Notas por unidade: alunos × disciplinas da grade, numa unidade OU no
     * consolidado do ano (uni_id ausente/vazio = consolidado).
     */
    public function notasPorUnidade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            // Diferença de contrato em relação ao web (lá é required): aqui a
            // ausência é o que pede o CONSOLIDADO do ano.
            'uni_id' => ['nullable', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $anlId = (int) $data['anl_id'];
        $turId = (int) $data['tur_id'];

        Guard::assertRegenciaTurma((int) $request->user()->fun_id, $turId);

        $ano   = AnoLetivo::findOrFail($anlId);
        $turma = Turma::with(['serie:ser_id,ser_nome', 'escola:esc_id,esc_nome'])->findOrFail($turId);

        $uniIdFiltro = ! empty($data['uni_id']) ? (int) $data['uni_id'] : null;
        $consolidado = $uniIdFiltro === null;

        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo', 'uni_dt_inicio', 'uni_dt_fim']);

        $uniIds = $consolidado
            ? $unidades->pluck('uni_id')->map(fn ($v) => (int) $v)->all()
            : [$uniIdFiltro];

        // Grade é por ANO LETIVO — sem o filtro, mistura grades de anos diferentes.
        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_anl_id', $anlId)
            ->where('gd.grd_fl_ativo', true)
            ->distinct()
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome']);

        // [dis_id][uni_id] => [aln_id => result]
        $res = [];
        foreach ($disciplinas as $d) {
            foreach ($uniIds as $uniId) {
                $res[$d->dis_id][$uniId] = CalculoNota::resultado($turId, (int) $d->dis_id, $uniId);
            }
        }

        // Faltas por disciplina na(s) unidade(s): [dis_id][aln_id] => qtd (manual tem precedência).
        $faltas = [];
        foreach ($disciplinas as $d) {
            $faltas[$d->dis_id] = FaltasAluno::totaisDisciplina($turId, (int) $d->dis_id, $uniIds);
        }

        // Roster deste relatório: SÓ matrícula ATIVA (o web filtra assim aqui —
        // é o boletim que inclui quem saiu, não este).
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function (Matricula $m) use ($disciplinas, $uniIds, $res, $consolidado, $faltas) {
                $alnId = (int) $m->aluno->aln_id;

                // As células saem NA ORDEM do array `disciplinas` — o app casa por
                // índice; o dis_id vai junto só para conferência/telas filtradas.
                $celulas = $disciplinas->map(function ($d) use ($alnId, $uniIds, $res, $consolidado, $faltas) {
                    if ($consolidado) {
                        $porUnidade = [];
                        foreach ($uniIds as $uniId) {
                            $porUnidade[$uniId] = $res[$d->dis_id][$uniId][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                        }
                        $r = CalculoNota::consolidar($porUnidade);
                    } else {
                        $r = $res[$d->dis_id][$uniIds[0]][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                    }

                    $cell = $this->celula($r);

                    return [
                        'dis_id'   => (int) $d->dis_id,
                        'texto'    => $cell['texto'],
                        'valor'    => $cell['valor'],
                        'sigla'    => $cell['sigla'],
                        'faltas'   => (int) ($faltas[$d->dis_id][$alnId] ?? 0),
                        'completo' => $cell['completo'],
                    ];
                });

                return [
                    'aln_id'           => $alnId,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'celulas'          => $celulas->values(),
                ];
            });

        // Rótulo do período (maiúsculo, ex.: "1º BIMESTRE") + intervalo de datas.
        // Paridade com o web: se o uni_id não pertence ao ano informado, o
        // relatório sai com os dados daquela unidade e rótulo "CONSOLIDADO".
        $uniSel = $uniIdFiltro ? $unidades->firstWhere('uni_id', $uniIdFiltro) : null;
        $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        $periodoLabel = $uniSel
            ? $uniSel->uni_numero . 'º ' . mb_strtoupper((string) $uniSel->uni_tipo, 'UTF-8')
            : 'CONSOLIDADO';
        $periodoDatas = $uniSel
            ? trim(($fmt($uniSel->uni_dt_inicio) ?? '') . ($uniSel->uni_dt_fim ? ' até ' . $fmt($uniSel->uni_dt_fim) : ''))
            : null;

        return response()->json([
            'contexto' => [
                'tur_nome'      => $turma->tur_nome,
                'ser_nome'      => $turma->serie?->ser_nome,
                'esc_nome'      => $turma->escola?->esc_nome,
                'anl_ano'       => $ano->anl_ano,
                'periodo_label' => $periodoLabel,
                'periodo_datas' => $periodoDatas,
                'consolidado'   => $consolidado,
            ],
            'disciplinas' => $disciplinas->map(fn ($d) => [
                'dis_id'   => (int) $d->dis_id,
                'dis_nome' => $d->dis_nome,
            ])->values(),
            'alunos' => $alunos->values(),
            // Explica um relatório vazio em vez de sair em branco silencioso.
            'avisos' => array_values(array_filter([
                $unidades->isEmpty()
                    ? 'Nenhuma unidade/período avaliativo cadastrado para o ano letivo. Cadastre em Parâmetros → Ano Letivo.'
                    : null,
                $disciplinas->isEmpty()
                    ? 'Grade disciplinar não cadastrada para esta série neste ano letivo. Cadastre em Parâmetros → Grade Disciplinar.'
                    : null,
                $alunos->isEmpty()
                    ? 'Nenhum aluno com matrícula ativa nesta turma.'
                    : null,
            ])),
        ]);
    }

    /**
     * Boletim avaliativo: um bloco por disciplina da grade, com a média de cada
     * unidade, faltas na matéria, M.F e resultado final.
     */
    public function boletimAvaliativo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['nullable', 'integer', 'exists:edu_disciplina,dis_id'],
        ]);

        $anlId = (int) $data['anl_id'];
        $turId = (int) $data['tur_id'];
        $disId = ! empty($data['dis_id']) ? (int) $data['dis_id'] : null;

        Guard::assertRegenciaTurma((int) $request->user()->fun_id, $turId);

        $ano   = AnoLetivo::findOrFail($anlId);
        $turma = Turma::with(['serie:ser_id,ser_nome', 'escola:esc_id,esc_nome'])->findOrFail($turId);

        // Unidades (bimestres) do ano — as colunas do boletim.
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => [
                'uni_id' => (int) $u->uni_id,
                'label'  => $u->uni_numero . 'º ' . ucfirst((string) $u->uni_tipo),
            ])
            ->values();

        // Disciplinas da grade (todas, mesmo sem nota lançada); ou só a filtrada.
        // Ordem: grd_ordem e depois o nome — é a ordem impressa no boletim.
        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_anl_id', $anlId)
            ->where('gd.grd_fl_ativo', true)
            ->when($disId, fn ($q) => $q->where('d.dis_id', $disId))
            ->distinct()
            ->orderBy('gd.grd_ordem')
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome', 'gd.grd_ordem']);

        // Roster: ativos + quem SAIU (com situação de saída, exceto remanejado —
        // remanejamento é troca de turma na mesma escola, não movimentação).
        // Sem dedup por aluno: 2 matrículas (saída + rematrícula) na mesma turma
        // aparecem ambas, como no modelo impresso.
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(fn ($s) => $s->whereNotNull('tma_tas_cod_saida')->where('tma_tas_cod_saida', '!=', Matricula::TAS_REMANEJADO));
            })
            ->whereNull('tma_deleted_at')
            ->with(['aluno:aln_id,aln_nome', 'situacaoSaida:tas_cod,tas_descricao,tas_descricao_enturmacao'])
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values();

        $turno = $turma->tur_turno
            ? (self::TURNOS[strtoupper((string) $turma->tur_turno)] ?? ucfirst(mb_strtolower((string) $turma->tur_turno)))
            : null;

        // Média de aprovação (numérica): por escola no ano letivo; senão a média
        // geral do ano. A escola vem da TURMA (o app não manda esc_id).
        $mediaEscola = DB::table('cfg_media_escola')
            ->where('mde_anl_id', $anlId)
            ->where('mde_esc_id', $turma->tur_esc_id)
            ->value('mde_media');
        $mediaAprov = $mediaEscola !== null
            ? (float) $mediaEscola
            : ($ano->anl_media_geral !== null ? (float) $ano->anl_media_geral : null);

        $uniIds = $unidades->pluck('uni_id')->all();
        $disIds = $disciplinas->pluck('dis_id')->map(fn ($v) => (int) $v)->all();

        // Fora do laço de disciplinas (o web repete estas duas consultas por
        // disciplina): professores da turma e recuperações finais são UMA query
        // cada para o boletim inteiro.
        $professores = DB::table('edu_turma_professor as tp')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'tp.tup_fun_id')
            ->where('tp.tup_tur_id', $turId)
            ->whereIn('tp.tup_dis_id', $disIds ?: [0])
            ->whereNull('tp.tup_deleted_at')
            ->distinct()
            ->get(['tp.tup_dis_id', 'f.fun_nome'])
            ->groupBy(fn ($r) => (int) $r->tup_dis_id)
            ->map(fn ($g) => $g->pluck('fun_nome')->unique()->implode(', '));

        $recuperacoesFinais = CalculoNota::recuperacoesFinais($turId);

        $blocos = $disciplinas->map(function ($d) use ($turId, $unidades, $uniIds, $alunos, $professores, $recuperacoesFinais) {
            $disId = (int) $d->dis_id;

            // Resultados por unidade: [uni_id][aln_id] => result.
            $res = [];
            foreach ($unidades as $u) {
                $res[$u['uni_id']] = CalculoNota::resultado($turId, $disId, $u['uni_id']);
            }

            // Faltas totais na matéria (todas as unidades): manual tem precedência.
            $faltas = FaltasAluno::totaisDisciplina($turId, $disId, $uniIds);
            // Recuperação final da disciplina: [aln] => valor (vale como M.F se maior).
            $recRow = $recuperacoesFinais[$disId] ?? [];

            $linhas = $alunos->map(fn (Matricula $m) => $this->linhaBoletim($m, $unidades, $res, $faltas, $recRow));

            return [
                'dis_id'   => $disId,
                'dis_nome' => $d->dis_nome,
                // `?:` e não só `??`: disciplina sem regência lançada cai no travessão,
                // igual ao web.
                'professor' => ($professores[$disId] ?? '') ?: '—',
                'alunos'   => $linhas->values(),
            ];
        });

        return response()->json([
            'contexto' => [
                'tur_nome'        => $turma->tur_nome,
                'ser_nome'        => $turma->serie?->ser_nome,
                'esc_nome'        => $turma->escola?->esc_nome,
                'anl_ano'         => $ano->anl_ano,
                'turno'           => $turno,
                'media_aprovacao' => $mediaAprov,
            ],
            'unidades'    => $unidades,
            'disciplinas' => $blocos->values(),
        ]);
    }

    // ============ Helpers ============

    /**
     * Uma linha do boletim (aluno × disciplina).
     *
     * @param  \Illuminate\Support\Collection<int, array{uni_id:int, label:string}>  $unidades
     * @param  array<int, array<int, array>>  $res  [uni_id][aln_id] => result
     * @param  array<int, int>  $faltas
     * @param  array<int, float>  $recRow
     */
    private function linhaBoletim(Matricula $m, Collection $unidades, array $res, array $faltas, array $recRow): array
    {
        $alnId = (int) $m->aluno->aln_id;
        $saiu  = $m->tma_situacao !== Matricula::SITUACAO_ATIVA;

        $medias = [];
        $porUnidade = [];
        foreach ($unidades as $u) {
            $r = $res[$u['uni_id']][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
            $porUnidade[$u['uni_id']] = $r;
            $texto = $this->celula($r)['texto'];
            // Quem saiu não mostra média do período — a linha é só a movimentação.
            $medias[$u['uni_id']] = ($saiu || $texto === '—') ? '' : $texto;
        }

        $consol  = CalculoNota::consolidar($porUnidade, $recRow[$alnId] ?? null);
        $mfTexto = $this->celula($consol)['texto'];

        // Resultado final SÓ existe depois do encerramento (tma_dt_encerramento):
        // é o tma_tas_cod_saida gravado lá, que já considera conselho, progressão
        // automática, etc. Não recalcular por M.F aqui — turma em curso não tem
        // resultado, e inventar um dá "Aprovado" antes da hora.
        $encerrado = ! $saiu && $m->tma_dt_encerramento !== null;
        $situacao  = $m->situacaoSaida?->tas_descricao_enturmacao ?: $m->situacaoSaida?->tas_descricao;

        if ($saiu) {
            $resultado = $situacao ?? 'Saída';       // movimentação (transferido, desistente, ...)
        } elseif ($encerrado) {
            $resultado = $situacao ?? '—';           // resultado do encerramento
        } else {
            $resultado = '—';                        // turma ainda não encerrada
        }

        return [
            // Sem aln_id de propósito: o roster NÃO é deduplicado, então duas
            // linhas podem ser do mesmo aluno — id aqui viraria chave duplicada
            // na lista do app. A linha é da MATRÍCULA, não do aluno.
            'aln_nome'  => $m->aluno->aln_nome,
            'faltas'    => (int) ($faltas[$alnId] ?? 0),
            // ARMADILHA: mapa uni_id => texto. Vai como objeto JSON de propósito —
            // um array PHP com chaves 0..n viraria lista e quebraria o acesso por
            // uni_id no app.
            'medias'    => (object) $medias,
            'mf'        => $saiu ? '-' : ($mfTexto === '—' ? '' : $mfTexto),
            'resultado' => $resultado,
        ];
    }

    /**
     * Formata um resultado do CalculoNota em célula exibível.
     *
     * `texto` é idêntico ao do web (NotaLookups::celula) — o app imprime esse
     * texto e não formata número por conta própria (vírgula decimal, conceito
     * por sigla). `valor`/`sigla` vêm junto para ordenar/colorir sem reparsear.
     *
     * `completo` só é verdadeiro na célula de UMA unidade: o consolidado do ano
     * é um agregado e o CalculoNota::consolidar não devolve completude.
     *
     * @param  array{tipo:?string, valor:?float, conceito:?array, completo?:bool}  $r
     */
    private function celula(array $r): array
    {
        $tipo = $r['tipo'] ?? null;

        if ($tipo === null) {
            $texto = '—';
        } elseif ($tipo === 'numerica') {
            $texto = $r['valor'] === null ? '—' : number_format((float) $r['valor'], 2, ',', '.');
        } else {
            $texto = $r['conceito']['sigla'] ?? '—';
        }

        return [
            'texto'    => $texto,
            'valor'    => isset($r['valor']) && $r['valor'] !== null ? (float) $r['valor'] : null,
            'sigla'    => $r['conceito']['sigla'] ?? null,
            'completo' => (bool) ($r['completo'] ?? false),
        ];
    }
}
