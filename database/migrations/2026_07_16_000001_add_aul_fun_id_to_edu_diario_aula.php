<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autoria histórica da aula.
 *
 * Antes, "quem deu a aula" era derivado de edu_turma_horario.trh_fun_id — ou seja, da
 * grade ATUAL. Ao trocar o professor da turma no meio do ano, o trh_fun_id era repassado
 * ao novo professor e todas as aulas já lançadas passavam a ser atribuídas a ele.
 * Gravar o professor na própria aula congela a autoria no momento do lançamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('edu_diario_aula', 'aul_fun_id')) {
            return;
        }

        Schema::table('edu_diario_aula', function (Blueprint $t) {
            $t->unsignedBigInteger('aul_fun_id')->nullable()->after('aul_user_id');
            $t->foreign('aul_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();
            $t->index(['aul_fun_id'], 'edu_aul_fun_idx');
        });

        // Backfill 1: grade atual. Correto para todo histórico anterior a esta migration,
        // já que até aqui nenhuma troca de professor era possível sem erro 500.
        DB::statement('
            UPDATE edu_diario_aula a
               SET aul_fun_id = h.trh_fun_id
              FROM edu_turma_horario h
             WHERE h.trh_id = a.aul_trh_id
               AND a.aul_fun_id IS NULL
               AND h.trh_fun_id IS NOT NULL
        ');

        // Backfill 2: quem lançou, para aulas cujo horário perdeu o professor.
        DB::statement('
            UPDATE edu_diario_aula a
               SET aul_fun_id = u.fun_id
              FROM users u
             WHERE u.id = a.aul_user_id
               AND a.aul_fun_id IS NULL
               AND u.fun_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('edu_diario_aula', 'aul_fun_id')) {
            return;
        }

        Schema::table('edu_diario_aula', function (Blueprint $t) {
            $t->dropForeign(['aul_fun_id']);
            $t->dropIndex('edu_aul_fun_idx');
            $t->dropColumn('aul_fun_id');
        });
    }
};
