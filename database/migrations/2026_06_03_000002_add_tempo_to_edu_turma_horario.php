<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_horario', function (Blueprint $table) {
            // Remover FK e unique antigas
            $table->dropForeign(['trh_grh_id']);
            $table->dropUnique(['trh_tur_id', 'trh_grh_id', 'trh_dia']);

            // grh_id vira nullable (retrocompatibilidade)
            $table->unsignedBigInteger('trh_grh_id')->nullable()->change();

            // Novo campo tempo (1–10)
            $table->unsignedTinyInteger('trh_tempo')->nullable()->after('trh_grh_id');

            // Nova unique por tempo + dia
            $table->unique(['trh_tur_id', 'trh_tempo', 'trh_dia'], 'edu_turma_horario_tur_tempo_dia_unique');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_horario', function (Blueprint $table) {
            $table->dropUnique('edu_turma_horario_tur_tempo_dia_unique');
            $table->dropColumn('trh_tempo');
            $table->unsignedBigInteger('trh_grh_id')->nullable(false)->change();
            $table->foreign('trh_grh_id')->references('grh_id')->on('edu_grade_horario')->restrictOnDelete();
            $table->unique(['trh_tur_id', 'trh_grh_id', 'trh_dia']);
        });
    }
};
