<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Faltas por aluno para relatórios. O lançamento manual da secretaria
 * (edu_nota_manual.nmn_faltas) tem precedência sobre a contagem do diário.
 */
class FaltasAluno
{
    /**
     * [aln_id => qtd] em (turma, disciplina, unidade). Manual sobrepõe o diário.
     *
     * @return array<int, int>
     */
    public static function porUnidade(int $turId, int $disId, int $uniId): array
    {
        $faltas = DB::table('edu_diario_falta as f')
            ->join('edu_diario_aula as a', 'a.aul_id', '=', 'f.fal_aul_id')
            ->where('a.aul_tur_id', $turId)
            ->where('a.aul_dis_id', $disId)
            ->where('a.aul_uni_id', $uniId)
            ->where('f.fal_fl_presente', false)
            ->whereNull('a.aul_deleted_at')
            ->whereNull('f.fal_deleted_at')
            ->groupBy('f.fal_aln_id')
            ->selectRaw('f.fal_aln_id, count(*) as q')
            ->pluck('q', 'f.fal_aln_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $manual = DB::table('edu_nota_manual')
            ->where('nmn_tur_id', $turId)
            ->where('nmn_dis_id', $disId)
            ->where('nmn_uni_id', $uniId)
            ->whereNull('nmn_deleted_at')
            ->whereNotNull('nmn_faltas')
            ->pluck('nmn_faltas', 'nmn_aln_id');

        foreach ($manual as $aln => $q) {
            $faltas[(int) $aln] = (int) $q;
        }

        return $faltas;
    }

    /**
     * [aln_id => total] somando todas as unidades de uma disciplina.
     *
     * @param  array<int,int>  $uniIds
     * @return array<int, int>
     */
    public static function totaisDisciplina(int $turId, int $disId, array $uniIds): array
    {
        $tot = [];
        foreach ($uniIds as $uniId) {
            foreach (self::porUnidade($turId, $disId, (int) $uniId) as $aln => $q) {
                $tot[$aln] = ($tot[$aln] ?? 0) + $q;
            }
        }

        return $tot;
    }
}
