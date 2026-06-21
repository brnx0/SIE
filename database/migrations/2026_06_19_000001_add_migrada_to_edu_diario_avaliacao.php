<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliação "migrada": cópia das notas de um aluno trazida de outra turma
 * (movimentação turma→turma). Fica na turma destino só para consulta/histórico,
 * editável, mas fora do cálculo de soma≤10 e da média final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_avaliacao', function (Blueprint $t) {
            $t->boolean('ava_fl_migrada')->default(false)->after('ava_fl_recuperacao');
            $t->unsignedBigInteger('ava_origem_tur_id')->nullable()->after('ava_fl_migrada');
            $t->unsignedBigInteger('ava_origem_ava_id')->nullable()->after('ava_origem_tur_id');

            $t->foreign('ava_origem_tur_id')->references('tur_id')->on('edu_turma')->nullOnDelete();
            $t->index(['ava_tur_id', 'ava_fl_migrada'], 'edu_ava_migrada_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_avaliacao', function (Blueprint $t) {
            $t->dropForeign(['ava_origem_tur_id']);
            $t->dropIndex('edu_ava_migrada_idx');
            $t->dropColumn(['ava_fl_migrada', 'ava_origem_tur_id', 'ava_origem_ava_id']);
        });
    }
};
