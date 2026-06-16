<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O tipo de avaliação descritiva da série passou a ser (por_disciplina | por_unidade).
 * Converte o valor legado "por_aluno" (1 registro por período) para "por_unidade",
 * que tem a mesma semântica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('edu_serie')
            ->where('ser_tipo_avaliacao_descritiva', 'por_aluno')
            ->update(['ser_tipo_avaliacao_descritiva' => 'por_unidade']);
    }

    public function down(): void
    {
        // Sem reversão: "por_aluno" foi descontinuado.
    }
};
