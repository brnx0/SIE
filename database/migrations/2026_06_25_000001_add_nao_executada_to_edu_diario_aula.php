<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aula NÃO executada: o professor marca o SEU tempo/data como sem aula.
 * Esse registro (turma, tempo, data) não conta falta nem presença e sai dos
 * relatórios de frequência. É por aula — marcar uma não anula as dos outros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_aula', function (Blueprint $table) {
            $table->boolean('aul_fl_nao_executada')->default(false)->after('aul_dt');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_aula', function (Blueprint $table) {
            $table->dropColumn('aul_fl_nao_executada');
        });
    }
};
