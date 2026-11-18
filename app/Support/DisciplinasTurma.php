<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quais disciplinas da grade valem para uma TURMA nas saídas (boletim, ata,
 * encerramento, histórico, relatórios, API do app).
 *
 * A grade disciplinar (série × ano) continua sendo o cadastro mestre — ordem,
 * nomes e o que pode ser lançado. Mas a turma só "tem" a disciplina quando:
 *   1. há professor ATIVO vinculado a ela na turma (edu_turma_professor), ou
 *   2. já existe lançamento dela na turma (nota, nota manual, recuperação final,
 *      aula/frequência, conteúdo, parecer descritivo ou diagnóstico) — para o
 *      professor removido não apagar o que já foi lançado.
 * Turma sem nenhum professor com disciplina e sem lançamento → nenhuma disciplina.
 *
 * Telas de ENTRADA (cadastro da turma, quadro de horário, lançamento manual,
 * lookups do diário para admin) seguem lendo a grade inteira.
 */
class DisciplinasTurma
{
    /** [tabela, coluna turma, coluna disciplina, coluna soft delete|null] */
    private const LANCAMENTOS = [
        ['edu_turma_professor', 'tup_tur_id', 'tup_dis_id', 'tup_deleted_at'],
        ['edu_diario_avaliacao', 'ava_tur_id', 'ava_dis_id', 'ava_deleted_at'],
        ['edu_nota_manual', 'nmn_tur_id', 'nmn_dis_id', 'nmn_deleted_at'],
        ['edu_recuperacao_final', 'rcf_tur_id', 'rcf_dis_id', 'rcf_deleted_at'],
        ['edu_diario_aula', 'aul_tur_id', 'aul_dis_id', 'aul_deleted_at'],
        ['edu_diario_conteudo', 'dco_tur_id', 'dco_dis_id', 'dco_deleted_at'],
        ['edu_diario_avaliacao_descritiva', 'dad_tur_id', 'dad_dis_id', 'dad_deleted_at'],
        ['edu_diario_diagnostico', 'dgn_tur_id', 'dgn_dis_id', 'dgn_deleted_at'],
    ];

    /**
     * Restringe uma query (normalmente sobre edu_grade_disciplinar) às
     * disciplinas da turma. $disCol é a coluna de disciplina da query externa.
     */
    public static function restringir($query, int $turId, string $disCol = 'gd.grd_dis_id')
    {
        $base = $query instanceof Builder ? $query : $query->getQuery();

        $base->where(function ($w) use ($turId, $disCol) {
            foreach (self::LANCAMENTOS as [$tabela, $colTur, $colDis, $colDel]) {
                $w->orWhereExists(function ($q) use ($tabela, $colTur, $colDis, $colDel, $turId, $disCol) {
                    $q->select(DB::raw(1))
                        ->from($tabela)
                        ->where($colTur, $turId)
                        ->whereColumn($colDis, $disCol)
                        ->when($colDel, fn ($q) => $q->whereNull($colDel));
                });
            }
        });

        return $query;
    }

    /**
     * IDs das disciplinas da grade (série × ano) que valem para a turma.
     *
     * @return array<int>
     */
    public static function ids(int $turId, int $serId, int $anlId): array
    {
        $q = DB::table('edu_grade_disciplinar as gd')
            ->where('gd.grd_ser_id', $serId)
            ->where('gd.grd_anl_id', $anlId)
            ->where('gd.grd_fl_ativo', true);

        return self::restringir($q, $turId)
            ->pluck('gd.grd_dis_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }
}
