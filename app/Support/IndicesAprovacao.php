<?php

namespace App\Support;

use App\Models\Diario\DiarioAvaliacao;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Support\Facades\DB;

/**
 * Índices de aprovação/reprovação por turma, em cada unidade (trimestre) e no ano.
 *
 * Usa as MESMAS regras do diário e do encerramento (CalculoNota +
 * critérios de CalculoEncerramento), só que apurando também por unidade:
 *
 *  · Disciplinas: grade disciplinar ativa da série no ano.
 *  · Critério: média da escola (cfg_media_escola) ou a do ano letivo; conceito
 *    mínimo da escola ou do ano (comparado pelo peso).
 *  · Unidade: aluno APROVADO se atingiu o critério em TODAS as disciplinas;
 *    REPROVADO se ficou abaixo em ao menos uma; PENDENTE se em alguma disciplina
 *    falta nota (avaliação já aplicada sem nota, ou nenhuma avaliação/nota).
 *  · Anual: média final de cada disciplina pelo CalculoNota::consolidar (com a
 *    recuperação final). Só nota — frequência não entra. PENDENTE se a
 *    disciplina estiver pendente em alguma unidade do ano.
 *  · Série sem nota (só descritiva/diagnóstico) ou de progressão automática:
 *    fora do índice ("sem avaliação por nota").
 *  · Alunos: matrículas ATIVAS da turma (como o diário e o encerramento).
 */
class IndicesAprovacao
{
    public const APROVADO  = 'aprovado';
    public const REPROVADO = 'reprovado';
    public const PENDENTE  = 'pendente';

    /**
     * Dados pré-carregados para um lote de turmas (preparar()) e memo de critério
     * e grade — na rede inteira evita ~10 consultas por turma.
     */
    private static array $cache = ['turmas' => [], 'alunos' => [], 'lanc' => [], 'criterio' => [], 'grade' => []];

    /** Carrega de uma vez turmas, alunos ativos e pares (disciplina, unidade) com lançamento. */
    public static function preparar(array $turIds): void
    {
        self::$cache = ['turmas' => [], 'alunos' => [], 'lanc' => [], 'criterio' => [], 'grade' => []];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $turIds))), 1000) as $lote) {
            foreach (DB::table('edu_turma as t')->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')->whereIn('t.tur_id', $lote)
                ->get(['t.tur_id', 't.tur_ser_id', 't.tur_anl_id', 't.tur_esc_id', 's.ser_fl_progressao_auto', DB::raw('s.ser_tipo_avaliacao::text as tav')]) as $t) {
                self::$cache['turmas'][(int) $t->tur_id] = $t;
            }
            // Mesmo recorte de CalculoNota::alunos(): matrículas ATIVAS.
            foreach (Matricula::query()->whereIn('tma_tur_id', $lote)->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')->get(['tma_tur_id', 'tma_aln_id']) as $m) {
                self::$cache['alunos'][(int) $m->tma_tur_id][] = (int) $m->tma_aln_id;
            }
            foreach (DiarioAvaliacao::query()->whereIn('ava_tur_id', $lote)->distinct()->get(['ava_tur_id', 'ava_dis_id', 'ava_uni_id']) as $a) {
                self::$cache['lanc'][(int) $a->ava_tur_id][$a->ava_dis_id . '|' . $a->ava_uni_id] = true;
            }
            foreach (DB::table('edu_nota_manual')->whereIn('nmn_tur_id', $lote)->whereNull('nmn_deleted_at')
                ->distinct()->get(['nmn_tur_id', 'nmn_dis_id', 'nmn_uni_id']) as $n) {
                self::$cache['lanc'][(int) $n->nmn_tur_id][$n->nmn_dis_id . '|' . $n->nmn_uni_id] = true;
            }
        }
        foreach (self::$cache['turmas'] as $id => $_) {
            self::$cache['alunos'][$id] ??= [];
            self::$cache['lanc'][$id] ??= [];
        }
    }

    /** Média mínima e peso do conceito mínimo (escola → ano letivo), memorizados. */
    private static function criterio(int $anlId, int $escId): array
    {
        return self::$cache['criterio']["$anlId|$escId"] ??= (function () use ($anlId, $escId) {
            $ano = AnoLetivo::find($anlId);
            $mde = DB::table('cfg_media_escola')->where('mde_anl_id', $anlId)->where('mde_esc_id', $escId)->first(['mde_media', 'mde_cnc_id']);
            $mediaRef = $mde && $mde->mde_media !== null ? (float) $mde->mde_media : ($ano?->anl_media_geral !== null ? (float) $ano->anl_media_geral : null);
            $cncMin = $mde && $mde->mde_cnc_id !== null ? (int) $mde->mde_cnc_id : ($ano?->anl_cnc_id_geral !== null ? (int) $ano->anl_cnc_id_geral : null);

            return [$mediaRef, $cncMin ? (int) DB::table('cfg_conceito')->where('cnc_id', $cncMin)->value('cnc_peso') : null];
        })();
    }

    /** Disciplinas da grade ativa da série no ano, memorizadas. */
    private static function grade(int $serId, int $anlId): array
    {
        return self::$cache['grade']["$serId|$anlId"] ??= DB::table('edu_grade_disciplinar')
            ->where('grd_ser_id', $serId)
            ->where('grd_anl_id', $anlId)
            ->where('grd_fl_ativo', true)
            ->pluck('grd_dis_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Unidades do ano em ordem, com rótulo ("1º Trimestre").
     *
     * @return array<int, array{uni_id:int, label:string}>
     */
    public static function unidades(int $anlId): array
    {
        return DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => [
                'uni_id' => (int) $u->uni_id,
                'label'  => $u->uni_numero . 'º ' . ucfirst(mb_strtolower((string) $u->uni_tipo)),
            ])
            ->all();
    }

    /**
     * Contagem por período ('u{uni_id}' e 'anual') para UMA turma.
     *
     * @return array{alunos:int, sem_avaliacao:bool, aviso:?string,
     *               periodos: array<string, array{aprovados:int, reprovados:int, pendentes:int}>}
     */
    public static function turma(int $turId, array $unidades): array
    {
        if (! isset(self::$cache['turmas'][$turId])) {
            self::preparar([$turId]);
        }
        $turma  = self::$cache['turmas'][$turId] ?? null;
        $alunos = self::$cache['alunos'][$turId] ?? [];
        $chaves   = array_merge(array_map(fn ($u) => 'u' . $u['uni_id'], $unidades), ['anual']);
        $zerado   = fn () => ['aprovados' => 0, 'reprovados' => 0, 'pendentes' => 0];
        $periodos = array_fill_keys($chaves, null);
        foreach ($chaves as $c) {
            $periodos[$c] = $zerado();
        }

        $base = ['alunos' => count($alunos), 'sem_avaliacao' => false, 'aviso' => null, 'periodos' => $periodos];
        if (! $turma || ! $alunos) {
            return $base;
        }

        $tipos = json_decode((string) $turma->tav, true);
        $tipos = is_array($tipos) ? $tipos : [];
        if (! array_intersect(['numerica', 'conceitual'], $tipos) || $turma->ser_fl_progressao_auto) {
            return ['sem_avaliacao' => true] + $base;
        }

        // Critério de aprovação (mesma precedência do encerramento: escola → ano).
        [$mediaRef, $pesoMin] = self::criterio((int) $turma->tur_anl_id, (int) $turma->tur_esc_id);
        $disIds = self::grade((int) $turma->tur_ser_id, (int) $turma->tur_anl_id);

        // Sem critério ou sem disciplinas não dá para classificar: todos pendentes.
        $aviso = null;
        if ($mediaRef === null) {
            $aviso = 'Média mínima de aprovação não configurada (Parâmetros → Ano Letivo ou Média por Escola).';
        } elseif (! $disIds) {
            $aviso = 'Série sem disciplinas na grade disciplinar do ano.';
        }
        if ($aviso) {
            foreach ($chaves as $c) {
                $periodos[$c]['pendentes'] = count($alunos);
            }

            return ['aviso' => $aviso, 'periodos' => $periodos] + $base;
        }

        // Resultado por disciplina × unidade × aluno. Só calcula onde há avaliação ou
        // média manual (pré-carregado em preparar()); o par sem lançamento fica [] (= pendente).
        $comLancamento = self::$cache['lanc'][$turId] ?? [];

        $res = [];
        foreach ($disIds as $dis) {
            foreach ($unidades as $u) {
                $res[$dis][$u['uni_id']] = isset($comLancamento[$dis . '|' . $u['uni_id']])
                    ? CalculoNota::resultado($turId, $dis, $u['uni_id'])
                    : [];
            }
        }
        $recs = CalculoNota::recuperacoesFinais($turId);

        // 'ok' | 'abaixo' | 'pendente' de um resultado contra o critério.
        $avaliar = function (?array $r) use ($mediaRef, $pesoMin): string {
            if (! $r || ($r['tipo'] ?? null) === null || ! ($r['completo'] ?? true)) {
                return 'pendente';
            }
            if ($r['tipo'] === 'numerica') {
                return $r['valor'] === null ? 'pendente' : ((float) $r['valor'] >= $mediaRef ? 'ok' : 'abaixo');
            }
            // Conceitual (faixa ou direto): compara o peso do conceito.
            $peso = $r['conceito']['peso'] ?? null;
            if ($peso === null) {
                return 'pendente';
            }

            return ($pesoMin === null || (int) $peso >= $pesoMin) ? 'ok' : 'abaixo';
        };

        // Situação do aluno a partir das situações por disciplina.
        $situacao = function (array $porDis): string {
            if (in_array('pendente', $porDis, true)) {
                return self::PENDENTE;
            }

            return in_array('abaixo', $porDis, true) ? self::REPROVADO : self::APROVADO;
        };

        foreach ($alunos as $aln) {
            $porUni = []; // [uni_id][dis] => situação
            $anual  = []; // [dis] => situação
            foreach ($disIds as $dis) {
                $pendenteNoAno = false;
                foreach ($unidades as $u) {
                    $st = $avaliar($res[$dis][$u['uni_id']][$aln] ?? null);
                    $porUni[$u['uni_id']][$dis] = $st;
                    $pendenteNoAno = $pendenteNoAno || $st === 'pendente';
                }

                if ($pendenteNoAno) {
                    $anual[$dis] = 'pendente';
                    continue;
                }
                $porUnidade = [];
                foreach ($unidades as $u) {
                    $porUnidade[$u['uni_id']] = $res[$dis][$u['uni_id']][$aln];
                }
                $anual[$dis] = $avaliar(CalculoNota::consolidar($porUnidade, $recs[$dis][$aln] ?? null) + ['completo' => true]);
            }

            foreach ($unidades as $u) {
                $periodos['u' . $u['uni_id']][$situacao($porUni[$u['uni_id']] ?? []) . 's']++;
            }
            $periodos['anual'][$situacao($anual) . 's']++;
        }

        $semCriterioConceito = $pesoMin === null && in_array('conceitual', $tipos, true)
            ? 'Conceito mínimo não configurado: todo conceito conta como aprovado.'
            : null;

        return ['aviso' => $semCriterioConceito, 'periodos' => $periodos] + $base;
    }

    /** Soma contagens de período (para subtotais por escola e total da rede). */
    public static function somar(array $a, array $b): array
    {
        foreach ($b as $chave => $v) {
            foreach (['aprovados', 'reprovados', 'pendentes'] as $campo) {
                $a[$chave][$campo] = ($a[$chave][$campo] ?? 0) + $v[$campo];
            }
        }

        return $a;
    }

    /** Percentuais sobre os alunos CLASSIFICADOS (aprovados + reprovados); pendentes fora. */
    public static function percentuais(array $p): array
    {
        $avaliados = $p['aprovados'] + $p['reprovados'];

        return [
            'avaliados'     => $avaliados,
            'pct_aprovacao' => $avaliados ? round($p['aprovados'] * 100 / $avaliados, 1) : null,
            'pct_reprovacao' => $avaliados ? round($p['reprovados'] * 100 / $avaliados, 1) : null,
        ];
    }
}
