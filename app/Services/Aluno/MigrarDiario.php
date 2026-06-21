<?php

namespace App\Services\Aluno;

use App\Models\Diario\DiarioAula;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioFalta;
use App\Models\Diario\DiarioNota;
use App\Models\Turma\Turma;

/**
 * Leva o histórico do diário do aluno para a turma destino numa movimentação
 * (remanejamento / reclassificação). As notas viram avaliações "migradas" na
 * turma destino: consulta/edição no diário, fora do cálculo de soma e média final.
 */
class MigrarDiario
{
    public function migrar(int $alnId, int $turOrigemId, Turma $turDestino): void
    {
        $this->migrarNotas($alnId, $turOrigemId, $turDestino);
        $this->migrarFaltas($alnId, $turOrigemId, $turDestino);
    }

    public function migrarNotas(int $alnId, int $turOrigemId, Turma $turDestino): void
    {
        // Notas do aluno nas avaliações ORIGINAIS (não migradas) da turma de origem.
        $notas = DiarioNota::query()
            ->where('nta_aln_id', $alnId)
            ->whereHas('avaliacao', fn ($q) => $q
                ->where('ava_tur_id', $turOrigemId)
                ->where('ava_fl_migrada', false))
            ->with('avaliacao')
            ->get();

        foreach ($notas as $nota) {
            $av = $nota->avaliacao;
            if (! $av) {
                continue;
            }

            // Avaliação espelho na turma destino (idempotente por origem_ava_id).
            $mirror = DiarioAvaliacao::firstOrCreate(
                [
                    'ava_tur_id'        => $turDestino->tur_id,
                    'ava_origem_ava_id' => $av->ava_id,
                ],
                [
                    'ava_user_id'        => $av->ava_user_id,
                    'ava_esc_id'         => $turDestino->tur_esc_id,
                    'ava_anl_id'         => $turDestino->tur_anl_id,
                    'ava_dis_id'         => $av->ava_dis_id,
                    'ava_uni_id'         => $av->ava_uni_id,
                    'ava_iav_id'         => $av->ava_iav_id,
                    'ava_tipo'           => $av->ava_tipo,
                    'ava_descricao'      => $av->ava_descricao,
                    'ava_dt'             => $av->ava_dt,
                    'ava_valor'          => $av->ava_valor,
                    'ava_fl_recuperacao' => $av->ava_fl_recuperacao,
                    'ava_fl_migrada'     => true,
                    'ava_origem_tur_id'  => $turOrigemId,
                ],
            );

            // Copia a nota do aluno (não sobrescreve se já migrada antes).
            DiarioNota::firstOrCreate(
                [
                    'nta_ava_id' => $mirror->ava_id,
                    'nta_aln_id' => $alnId,
                ],
                [
                    'nta_valor'  => $nota->nta_valor,
                    'nta_cnc_id' => $nota->nta_cnc_id,
                ],
            );
        }
    }

    public function migrarFaltas(int $alnId, int $turOrigemId, Turma $turDestino): void
    {
        // Ausências do aluno nas aulas ORIGINAIS da turma de origem.
        $faltas = DiarioFalta::query()
            ->where('fal_aln_id', $alnId)
            ->where('fal_fl_presente', false)
            ->whereHas('aula', fn ($q) => $q
                ->where('aul_tur_id', $turOrigemId)
                ->where('aul_fl_migrada', false))
            ->with('aula')
            ->get();

        foreach ($faltas as $falta) {
            $aula = $falta->aula;
            if (! $aula) {
                continue;
            }

            // Aula espelho na turma destino (mantém disciplina/unidade/data de origem).
            $mirror = DiarioAula::firstOrCreate(
                [
                    'aul_tur_id'        => $turDestino->tur_id,
                    'aul_origem_aul_id' => $aula->aul_id,
                ],
                [
                    'aul_user_id'       => $aula->aul_user_id,
                    'aul_esc_id'        => $turDestino->tur_esc_id,
                    'aul_anl_id'        => $turDestino->tur_anl_id,
                    'aul_uni_id'        => $aula->aul_uni_id,
                    'aul_trh_id'        => $aula->aul_trh_id,
                    'aul_dis_id'        => $aula->aul_dis_id,
                    'aul_dt'            => $aula->aul_dt,
                    'aul_fl_migrada'    => true,
                    'aul_origem_tur_id' => $turOrigemId,
                ],
            );

            DiarioFalta::firstOrCreate(
                [
                    'fal_aul_id' => $mirror->aul_id,
                    'fal_aln_id' => $alnId,
                ],
                ['fal_fl_presente' => false],
            );
        }
    }
}
