<?php

namespace App\Support\Diario;

use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Faltas justificadas no cálculo de frequência (relatório mensal, API v1 e
 * frequência anual do encerramento — a mesma regra nos três).
 *
 *  · A justificativa (Secretaria › Justificativa de Falta) é por ALUNO e
 *    intervalo de datas; vale para as faltas lançadas nesses dias.
 *  · Motivo com "abona falta" (cfg_motivo_baixa_frequencia.mbf_fl_abona): a
 *    falta deixa de contar no percentual. Sem abono: aparece como justificada,
 *    mas continua contando.
 */
class JustificativasFalta
{
    /**
     * Dias justificados por aluno no intervalo: [aln_id][Y-m-d] => abona (bool).
     *
     * @param  array<int>  $alnIds
     * @return array<int, array<string, bool>>
     */
    public static function porDia(array $alnIds, string $ini, string $fim): array
    {
        if (! $alnIds) {
            return [];
        }

        $out = [];
        $rows = DB::table('edu_justificativa_falta as j')
            ->leftJoin('cfg_motivo_baixa_frequencia as m', 'm.mbf_id', '=', 'j.jfa_mbf_id')
            ->whereIn('j.jfa_aln_id', $alnIds)
            ->whereNull('j.jfa_deleted_at')
            ->whereDate('j.jfa_dt_inicio', '<=', $fim)
            ->whereDate('j.jfa_dt_fim', '>=', $ini)
            ->get(['j.jfa_aln_id', 'j.jfa_dt_inicio', 'j.jfa_dt_fim', 'm.mbf_fl_abona']);

        foreach ($rows as $j) {
            $de  = max(substr((string) $j->jfa_dt_inicio, 0, 10), $ini);
            $ate = min(substr((string) $j->jfa_dt_fim, 0, 10), $fim);
            foreach (CarbonPeriod::create($de, $ate) as $dia) {
                $d = $dia->toDateString();
                // Dois motivos no mesmo dia (não deveria ocorrer): basta um abonar.
                $out[(int) $j->jfa_aln_id][$d] = ($out[(int) $j->jfa_aln_id][$d] ?? false) || (bool) $j->mbf_fl_abona;
            }
        }

        return $out;
    }

    /**
     * Faltas do aluno no período, separando as justificadas e as abonadas.
     *
     * @param  'dias'|'aulas'  $modo         dias: falta = dia em que faltou a TODAS as aulas executadas
     * @param  array<int, bool>  $aus        [aul_id] => true (ausências do aluno)
     * @param  iterable<string, array<int>>  $aulasPorDia  [Y-m-d] => aul_ids executadas no dia
     * @param  array<string, bool>  $justDia  [Y-m-d] => abona (dias justificados do aluno)
     * @return array{faltas:int, justificadas:int, abonadas:int}
     */
    public static function contar(string $modo, array $aus, iterable $aulasPorDia, array $justDia): array
    {
        $faltas = $justificadas = $abonadas = 0;

        foreach ($aulasPorDia as $dia => $auls) {
            if (empty($auls)) {
                continue;
            }
            $ausentes = count(array_intersect($auls, array_keys($aus)));
            $n = $modo === 'dias'
                ? (int) ($ausentes === count($auls))
                : $ausentes;
            if (! $n) {
                continue;
            }

            $faltas += $n;
            if (array_key_exists((string) $dia, $justDia)) {
                $justificadas += $n;
                if ($justDia[(string) $dia]) {
                    $abonadas += $n;
                }
            }
        }

        return ['faltas' => $faltas, 'justificadas' => $justificadas, 'abonadas' => $abonadas];
    }
}
