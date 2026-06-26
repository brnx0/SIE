<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcador de encerramento por aluno na turma. A situação final vai em
 * tma_tas_cod_saida; tma_dt_encerramento distingue um resultado de encerramento
 * de uma saída por movimentação (transferência, remanejamento, etc.).
 * Cancelar o encerramento limpa ambos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->timestamp('tma_dt_encerramento')->nullable()->after('tma_tas_cod_saida');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->dropColumn('tma_dt_encerramento');
        });
    }
};
