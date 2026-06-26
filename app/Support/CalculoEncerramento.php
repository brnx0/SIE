<?php

namespace App\Support;

use App\Models\Parametro\AnoLetivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cálculo da situação final do aluno no ENCERRAMENTO da turma.
 *
 * Ordem de decisão por aluno (ativo):
 *  1. Série não avaliativa OU progressão automática → Aprovado (6).
 *  2. Aprovado pelo Conselho (marcado manualmente) → Apr. Conselho (7), ignora frequência;
 *     limitado a anl_qtd_materias_conselho disciplinas reprovadas em nota (NULL = ilimitado).
 *  3. Avaliativa: nota (todas as disciplinas ≥ média) + frequência anual (≥ mínima).
 *       ok+ok → Aprovado (6); só nota → Reprovado por Nota (9);
 *       só falta → Reprovado por Falta (10); nota+falta → Reprovado por Nota e Falta (11).
 */
class CalculoEncerramento
{
    public const APROVADO             = 6;
    public const CONSELHO             = 7;
    public const REPROVADO_NOTA       = 9;
    public const REPROVADO_FALTA      = 10;
    public const REPROVADO_NOTA_FALTA = 11;

    /**
     * @param  array<int>  $conselhoAlnIds  alunos marcados como "aprovado pelo conselho"
     * @return array{resultados: array<int, array{tas_cod:int, reprovou_nota:bool, reprovou_falta:bool, conselho:bool, frequencia:?float}>, erros: array<int, string>}
     */
    public static function calcular(int $turId, array $conselhoAlnIds = []): array
    {
        $turma = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->first(['t.tur_id', 't.tur_ser_id', 't.tur_anl_id', 't.tur_esc_id', 's.seg_id', 's.ser_fl_progressao_auto', DB::raw('s.ser_tipo_avaliacao::text as tav')]);

        if (! $turma) {
            return ['resultados' => [], 'erros' => []];
        }

        $ano    = AnoLetivo::find($turma->tur_anl_id);
        $tipos  = json_decode((string) $turma->tav, true) ?: [];
        $tipos  = is_array($tipos) ? $tipos : [];
        $avaliativa = (bool) array_intersect(['numerica', 'conceitual'], $tipos);
        $progAuto   = (bool) $turma->ser_fl_progressao_auto;

        $alunos = self::alunosAtivos($turId);

        // Não avaliativa ou progressão automática → todos Aprovados.
        if (! $avaliativa || $progAuto) {
            $res = [];
            foreach ($alunos as $a) {
                $res[$a] = ['tas_cod' => self::APROVADO, 'reprovou_nota' => false, 'reprovou_falta' => false, 'conselho' => false, 'frequencia' => null];
            }

            return ['resultados' => $res, 'erros' => []];
        }

        // Média de referência (escola tem precedência) + conceito mínimo + frequência mínima + teto do conselho.
        $mde     = DB::table('cfg_media_escola')->where('mde_anl_id', $turma->tur_anl_id)->where('mde_esc_id', $turma->tur_esc_id)->first(['mde_media', 'mde_cnc_id']);
        $mediaRef = $mde && $mde->mde_media !== null ? (float) $mde->mde_media : (float) $ano->anl_media_geral;
        $cncMin   = $mde && $mde->mde_cnc_id !== null ? (int) $mde->mde_cnc_id : ($ano->anl_cnc_id_geral !== null ? (int) $ano->anl_cnc_id_geral : null);
        $pesoMin  = $cncMin ? (int) DB::table('cfg_conceito')->where('cnc_id', $cncMin)->value('cnc_peso') : null;
        $freqMin  = (float) $ano->anl_frequencia_minima;
        $qtdConselho = $ano->anl_qtd_materias_conselho !== null ? (int) $ano->anl_qtd_materias_conselho : null;

        $disIds = DB::table('edu_grade_disciplinar')->where('grd_ser_id', $turma->tur_ser_id)->where('grd_fl_ativo', true)->pluck('grd_dis_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $uniIds = DB::table('cfg_unidade')->where('uni_anl_id', $turma->tur_anl_id)->pluck('uni_id')->map(fn ($v) => (int) $v)->all();

        // Resultado por (disciplina, aluno, unidade).
        $byDis = [];
        foreach ($disIds as $dis) {
            foreach ($uniIds as $uni) {
                foreach (CalculoNota::resultado($turId, $dis, $uni) as $aln => $r) {
                    $byDis[$dis][(int) $aln][$uni] = $r;
                }
            }
        }

        // Disciplinas reprovadas em NOTA por aluno.
        $reprovDis = function (int $aln) use ($disIds, $byDis, $mediaRef, $pesoMin): array {
            $lista = [];
            foreach ($disIds as $dis) {
                $final = CalculoNota::consolidar($byDis[$dis][$aln] ?? []);
                if ($final['tipo'] === null) {
                    $lista[] = $dis; // sem nota → não atingiu a média
                } elseif ($final['tipo'] === 'numerica') {
                    if ((float) ($final['valor'] ?? 0) < $mediaRef) {
                        $lista[] = $dis;
                    }
                } else { // conceitual
                    $peso = (int) ($final['conceito']['peso'] ?? 0);
                    if ($pesoMin !== null && $peso < $pesoMin) {
                        $lista[] = $dis;
                    }
                }
            }

            return $lista;
        };

        $freq = self::frequenciaAnual($turId, $turma, $ano, $alunos);

        $res = [];
        $erros = [];
        foreach ($alunos as $a) {
            $repNotaDis = $reprovDis($a);
            $repNota    = ! empty($repNotaDis);
            $repFalta   = ($freq[$a] ?? 100.0) < $freqMin;

            if (in_array($a, $conselhoAlnIds, true)) {
                if ($qtdConselho !== null && count($repNotaDis) > $qtdConselho) {
                    $erros[$a] = "O conselho pode aprovar no máximo {$qtdConselho} matéria(s); este aluno tem ".count($repNotaDis).' reprovada(s) em nota.';

                    continue;
                }
                $res[$a] = ['tas_cod' => self::CONSELHO, 'reprovou_nota' => $repNota, 'reprovou_falta' => $repFalta, 'conselho' => true, 'frequencia' => $freq[$a] ?? null];

                continue;
            }

            $cod = (! $repNota && ! $repFalta) ? self::APROVADO
                : ($repNota && $repFalta ? self::REPROVADO_NOTA_FALTA
                : ($repNota ? self::REPROVADO_NOTA : self::REPROVADO_FALTA));

            $res[$a] = ['tas_cod' => $cod, 'reprovou_nota' => $repNota, 'reprovou_falta' => $repFalta, 'conselho' => false, 'frequencia' => $freq[$a] ?? null];
        }

        return ['resultados' => $res, 'erros' => $erros];
    }

    /** Frequência anual (%) por aluno — mesmo método do relatório mensal, sobre o ano todo. */
    private static function frequenciaAnual(int $turId, $turma, $ano, array $alunos): array
    {
        $aulas = DB::table('edu_diario_aula')
            ->where('aul_tur_id', $turId)
            ->where('aul_fl_migrada', false)
            ->whereNull('aul_deleted_at')
            ->whereYear('aul_dt', $ano->anl_ano)
            ->get(['aul_id', 'aul_dt', 'aul_fl_nao_executada']);

        $exec   = $aulas->where('aul_fl_nao_executada', false)->values();
        $aulIds = $exec->pluck('aul_id')->map(fn ($v) => (int) $v)->all();

        $absent = [];
        foreach (DB::table('edu_diario_falta')->whereIn('fal_aul_id', $aulIds ?: [0])->where('fal_fl_presente', false)->whereNull('fal_deleted_at')->get(['fal_aln_id', 'fal_aul_id']) as $f) {
            $absent[(int) $f->fal_aln_id][(int) $f->fal_aul_id] = true;
        }

        $totalAulas  = count($aulIds);
        $aulasPorDia = $exec->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))->map(fn ($g) => $g->pluck('aul_id')->map(fn ($v) => (int) $v)->all());

        $diasParam   = self::diasLetivosAno((int) $ano->anl_id);
        $diasNaoExec = $aulas->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))->filter(fn ($g) => $g->every(fn ($a) => (bool) $a->aul_fl_nao_executada))->count();
        $diasLetivos = $diasParam !== null ? max(0, $diasParam - $diasNaoExec) : null;

        $turSerId  = (int) $turma->tur_ser_id;
        $serPorAln = DB::table('edu_turma_aluno')->where('tma_tur_id', $turId)->where('tma_situacao', 'ATIVA')->whereNull('tma_deleted_at')->pluck('tma_ser_id', 'tma_aln_id');
        $serIds    = collect($serPorAln)->map(fn ($v) => (int) ($v ?? $turSerId))->push($turSerId)->unique()->values()->all();
        $segPorSer = DB::table('edu_serie as s')->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')->whereIn('s.ser_id', $serIds ?: [0])->pluck('seg.seg_nome_reduzido', 's.ser_id')->all();
        $turmaSeg  = DB::table('edu_segmento')->where('seg_id', $turma->seg_id)->value('seg_nome_reduzido');

        $out = [];
        foreach ($alunos as $aln) {
            $serId = (int) ($serPorAln[$aln] ?? $turSerId);
            $seg   = (string) ($segPorSer[$serId] ?? $turmaSeg ?? '');
            $modo  = self::modoPorSegmento($seg);
            $aus   = $absent[$aln] ?? [];

            if ($modo === 'dias') {
                $diasFalta = 0;
                foreach ($aulasPorDia as $auls) {
                    if (! empty($auls) && count(array_intersect($auls, array_keys($aus))) === count($auls)) {
                        $diasFalta++;
                    }
                }
                $base   = $diasLetivos;
                $faltas = $diasFalta;
            } else {
                $base   = $totalAulas;
                $faltas = count($aus);
            }

            // Sem base (diário vazio / dias letivos não cadastrados) → não penaliza por frequência.
            $out[$aln] = ($base && $base > 0) ? round(max(0, $base - $faltas) / $base * 100, 1) : 100.0;
        }

        return $out;
    }

    private static function modoPorSegmento(string $segNome): string
    {
        $n = mb_strtoupper(Str::ascii($segNome), 'UTF-8');

        if (str_contains($n, 'FUNDAMENTAL II') || str_contains($n, 'EJA') || str_contains($n, 'CRECHE')) {
            return 'aulas';
        }
        if (str_contains($n, 'FUNDAMENTAL') || str_contains($n, 'PRE')) {
            return 'dias';
        }

        return 'aulas';
    }

    /** Soma dos dias letivos cadastrados (todos os meses) para o ano. */
    private static function diasLetivosAno(int $anlId): ?int
    {
        $raw = DB::table('cfg_dias_letivos')->where('dlt_anl_id', $anlId)->value('dlt_meses');
        if (! $raw) {
            return null;
        }
        $meses = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($meses)) {
            return null;
        }

        return (int) array_sum(array_map('intval', $meses));
    }

    /** @return array<int> */
    private static function alunosAtivos(int $turId): array
    {
        return DB::table('edu_turma_aluno')
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', 'ATIVA')
            ->whereNull('tma_deleted_at')
            ->pluck('tma_aln_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }
}
