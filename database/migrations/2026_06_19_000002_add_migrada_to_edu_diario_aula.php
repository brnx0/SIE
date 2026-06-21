<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aula "migrada": cópia da frequência de um aluno trazida de outra turma
 * (movimentação). Mantém disciplina/unidade/data de origem para os relatórios
 * contarem as faltas na turma destino; fica fora do lançamento de frequência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_aula', function (Blueprint $t) {
            $t->boolean('aul_fl_migrada')->default(false)->after('aul_dt');
            $t->unsignedBigInteger('aul_origem_tur_id')->nullable()->after('aul_fl_migrada');
            $t->unsignedBigInteger('aul_origem_aul_id')->nullable()->after('aul_origem_tur_id');

            $t->index(['aul_tur_id', 'aul_fl_migrada'], 'edu_aul_migrada_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_aula', function (Blueprint $t) {
            $t->dropIndex('edu_aul_migrada_idx');
            $t->dropColumn(['aul_fl_migrada', 'aul_origem_tur_id', 'aul_origem_aul_id']);
        });
    }
};
