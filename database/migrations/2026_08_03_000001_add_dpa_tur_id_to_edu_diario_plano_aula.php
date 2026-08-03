<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de aula continua sendo por SÉRIE (dpa_ser_id). Em séries marcadas como
 * turma multi (`ser_fl_multi`), o professor pode OPCIONALMENTE apontar o plano
 * para uma turma específica daquela série — daí a coluna nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->unsignedBigInteger('dpa_tur_id')->nullable()->after('dpa_ser_id');
            $table->foreign('dpa_tur_id')->references('tur_id')->on('edu_turma')->nullOnDelete();
            $table->index('dpa_tur_id');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->dropForeign(['dpa_tur_id']);
            $table->dropIndex(['dpa_tur_id']);
            $table->dropColumn('dpa_tur_id');
        });
    }
};
