<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordem de exibição do tipo de questionário.
 *
 * Define em que sequência os questionários aparecem ao montar a visita técnica.
 * A reordenação por inserção fica no CatalogoSimplesController. Backfill inicial
 * pela ordem alfabética atual, para os registros já cadastrados terem posição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_tipo_questionario', function (Blueprint $table) {
            $table->integer('tpq_ordem')->default(1)->after('tpq_nome');
        });

        DB::statement('
            WITH ord AS (
                SELECT tpq_id, ROW_NUMBER() OVER (ORDER BY tpq_nome, tpq_id) AS rn
                FROM ali_tipo_questionario
            )
            UPDATE ali_tipo_questionario t
            SET tpq_ordem = ord.rn
            FROM ord
            WHERE t.tpq_id = ord.tpq_id
        ');
    }

    public function down(): void
    {
        Schema::table('ali_tipo_questionario', function (Blueprint $table) {
            $table->dropColumn('tpq_ordem');
        });
    }
};
