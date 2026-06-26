<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineage da duplicação de turmas: a turma criada ao duplicar aponta para a
 * turma de origem (ano anterior). Usado para indicar "já duplicada" e impedir
 * duplicar a mesma turma duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma', function (Blueprint $t) {
            $t->unsignedBigInteger('tur_origem_tur_id')->nullable()->after('tur_anl_id');
            $t->foreign('tur_origem_tur_id')->references('tur_id')->on('edu_turma')->nullOnDelete();
            $t->index('tur_origem_tur_id', 'edu_tur_origem_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma', function (Blueprint $t) {
            $t->dropForeign(['tur_origem_tur_id']);
            $t->dropIndex('edu_tur_origem_idx');
            $t->dropColumn('tur_origem_tur_id');
        });
    }
};
