<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineage da renovação de matrícula: a matrícula criada na confirmação de
 * renovação (ano seguinte) aponta para a matrícula de origem (ano encerrado).
 * Usado para indicar "já renovado" e para remover a matrícula nova caso o
 * encerramento do aluno seja cancelado (a situação pode mudar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->unsignedBigInteger('tma_origem_tma_id')->nullable()->after('tma_dt_encerramento');
            $t->foreign('tma_origem_tma_id')->references('tma_id')->on('edu_turma_aluno')->nullOnDelete();
            $t->index('tma_origem_tma_id', 'edu_tma_origem_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->dropForeign(['tma_origem_tma_id']);
            $t->dropIndex('edu_tma_origem_idx');
            $t->dropColumn('tma_origem_tma_id');
        });
    }
};
