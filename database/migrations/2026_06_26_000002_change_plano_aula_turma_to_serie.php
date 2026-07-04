<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de aula regular passa a ser por SÉRIE (não mais por turma). Adiciona
 * dpa_ser_id (preenchido pela série da turma), remove dpa_tur_id e troca o
 * índice único (turma) por um índice comum (mantém planos existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $t) {
            $t->unsignedBigInteger('dpa_ser_id')->nullable()->after('dpa_tur_id');
        });

        // Backfill: série da turma de origem.
        DB::statement('
            UPDATE edu_diario_plano_aula p
            SET dpa_ser_id = t.tur_ser_id
            FROM edu_turma t
            WHERE t.tur_id = p.dpa_tur_id
        ');

        // Índice único era por turma — remove (planos passam a ser por série, sem único).
        DB::statement('DROP INDEX IF EXISTS edu_diario_plano_aula_unico');

        Schema::table('edu_diario_plano_aula', function (Blueprint $t) {
            $t->dropForeign(['dpa_tur_id']);
            $t->dropIndex('edu_diario_plano_aula_tur_idx');
            $t->dropColumn('dpa_tur_id');

            $t->foreign('dpa_ser_id')->references('ser_id')->on('edu_serie')->restrictOnDelete();
            $t->index('dpa_ser_id', 'edu_diario_plano_aula_ser_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $t) {
            $t->unsignedBigInteger('dpa_tur_id')->nullable()->after('dpa_ser_id');
        });

        Schema::table('edu_diario_plano_aula', function (Blueprint $t) {
            $t->dropForeign(['dpa_ser_id']);
            $t->dropIndex('edu_diario_plano_aula_ser_idx');
            $t->dropColumn('dpa_ser_id');

            $t->foreign('dpa_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $t->index('dpa_tur_id', 'edu_diario_plano_aula_tur_idx');
        });
    }
};
