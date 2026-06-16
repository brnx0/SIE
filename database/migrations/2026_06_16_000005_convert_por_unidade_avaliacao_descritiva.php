<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O tipo de avaliação descritiva da série é (por_aluno | por_disciplina).
 * Converte o valor legado "por_unidade" (1 registro por período) para "por_aluno",
 * que tem a mesma semântica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('edu_serie')
            ->where('ser_tipo_avaliacao_descritiva', 'por_unidade')
            ->update(['ser_tipo_avaliacao_descritiva' => 'por_aluno']);
    }

    public function down(): void
    {
        // Sem reversão: "por_unidade" foi descontinuado.
    }
};
