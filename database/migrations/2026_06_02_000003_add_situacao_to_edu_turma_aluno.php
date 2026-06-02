<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->smallInteger('tma_tas_cod_entrada')->default(1)->after('tma_situacao');
            $table->smallInteger('tma_tas_cod_saida')->nullable()->after('tma_tas_cod_entrada');
            $table->boolean('tma_fl_renovado')->default(false)->after('tma_tas_cod_saida');

            $table->foreign('tma_tas_cod_entrada')->references('tas_cod')->on('edu_turma_aluno_situacao');
            $table->foreign('tma_tas_cod_saida')->references('tas_cod')->on('edu_turma_aluno_situacao');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->dropForeign(['tma_tas_cod_entrada']);
            $table->dropForeign(['tma_tas_cod_saida']);
            $table->dropColumn(['tma_tas_cod_entrada', 'tma_tas_cod_saida', 'tma_fl_renovado']);
        });
    }
};
