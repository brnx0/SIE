<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico manual: conceito passa a ser TEXTO LIVRE.
 *
 * O município de origem do aluno pode usar siglas fora do catálogo local
 * (cfg_conceito) — ex.: R, S, H. `hmd_cnc_id` (FK ao catálogo) fica mantida
 * para os registros antigos, mas os lançamentos novos gravam a sigla digitada
 * em `hmd_conceito`. O backfill copia a sigla do catálogo para os já lançados,
 * de modo que os relatórios possam ler uma coluna só.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_historico_manual_disciplina', function (Blueprint $t) {
            $t->string('hmd_conceito', 10)->nullable()->after('hmd_cnc_id');
        });

    }

    public function down(): void
    {
        Schema::table('edu_historico_manual_disciplina', function (Blueprint $t) {
            $t->dropColumn('hmd_conceito');
        });
    }
};
