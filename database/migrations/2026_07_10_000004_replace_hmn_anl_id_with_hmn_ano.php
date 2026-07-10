<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico manual: o ano letivo passa a ser DIGITADO (escolaridade legada / fora
 * da rede pode não existir em cfg_ano_letivo). Troca a FK hmn_anl_id → cfg_ano_letivo
 * por uma coluna numérica hmn_ano (ano). Migra o dado existente pelo anl_ano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('edu_historico_manual', 'hmn_ano')) {
            return;
        }

        Schema::table('edu_historico_manual', function (Blueprint $t) {
            $t->unsignedSmallInteger('hmn_ano')->nullable()->after('hmn_aln_id');
        });

        // Backfill a partir do ano letivo vinculado.
        DB::statement('
            UPDATE edu_historico_manual hm
            SET hmn_ano = al.anl_ano
            FROM cfg_ano_letivo al
            WHERE al.anl_id = hm.hmn_anl_id
        ');

        // Remove índice único antigo e a FK/coluna do ano letivo.
        DB::statement('DROP INDEX IF EXISTS edu_hmn_unico');

        Schema::table('edu_historico_manual', function (Blueprint $t) {
            $t->dropForeign(['hmn_anl_id']);
            $t->dropColumn('hmn_anl_id');
        });

        DB::statement('ALTER TABLE edu_historico_manual ALTER COLUMN hmn_ano SET NOT NULL');

        // 1 lançamento manual por (aluno, ano).
        DB::statement('
            CREATE UNIQUE INDEX edu_hmn_unico ON edu_historico_manual
            (hmn_aln_id, hmn_ano)
            WHERE hmn_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        if (Schema::hasColumn('edu_historico_manual', 'hmn_anl_id')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS edu_hmn_unico');

        Schema::table('edu_historico_manual', function (Blueprint $t) {
            $t->unsignedBigInteger('hmn_anl_id')->nullable()->after('hmn_aln_id');
        });

        // Best-effort: reassocia pelo ano (quando existir ano letivo correspondente).
        DB::statement('
            UPDATE edu_historico_manual hm
            SET hmn_anl_id = al.anl_id
            FROM cfg_ano_letivo al
            WHERE al.anl_ano = hm.hmn_ano
        ');

        Schema::table('edu_historico_manual', function (Blueprint $t) {
            $t->foreign('hmn_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $t->dropColumn('hmn_ano');
        });

        DB::statement('
            CREATE UNIQUE INDEX edu_hmn_unico ON edu_historico_manual
            (hmn_aln_id, hmn_anl_id)
            WHERE hmn_deleted_at IS NULL
        ');
    }
};
