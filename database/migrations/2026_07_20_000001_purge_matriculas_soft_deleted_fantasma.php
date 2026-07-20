<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpeza de dados: remove de fato as matrículas que ficaram apenas SOFT-DELETED.
 *
 * Contexto do bug: ao desfazer o encerramento de um aluno (removerRenovacoes) ou
 * desfazer uma movimentação (DesfazerMovimentacao), o código usava ->delete() em
 * um modelo com SoftDeletes, então a linha permanecia em edu_turma_aluno com
 * tma_deleted_at preenchido. Como tma_tur_id tem FK restrictOnDelete, essa linha
 * "fantasma" impedia a exclusão da turma de destino — o TurmaController::destroy
 * (e os equivalentes AEE/ATIVIDADE) acusavam "alunos alocados" via
 * matriculas()->withTrashed(), mesmo sem nenhum aluno ativo.
 *
 * As duas operações passaram a usar forceDelete. Esta migration corrige os dados
 * já existentes. É seguro apagar TODA matrícula soft-deleted: nenhuma parte do
 * sistema restaura (não há Matricula::...->restore()); as únicas rotinas que
 * soft-deletavam matrícula eram justamente as duas corrigidas. As FKs que
 * referenciam tma_id (mva_tma_id_origem, mva_tma_id_destino, tma_origem_tma_id)
 * são nullOnDelete, então os vínculos são anulados sem violar constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $qtd = DB::table('edu_turma_aluno')->whereNotNull('tma_deleted_at')->count();

        // DELETE físico (bypassa o SoftDeletes do model, que só marcaria de novo).
        DB::table('edu_turma_aluno')->whereNotNull('tma_deleted_at')->delete();

        echo "  purge_matriculas_soft_deleted_fantasma: {$qtd} matrícula(s) fantasma removida(s).\n";
    }

    public function down(): void
    {
        // Sem rollback: linhas apagadas fisicamente não podem ser restauradas.
    }
};
