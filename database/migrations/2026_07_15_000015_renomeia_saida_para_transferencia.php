<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Saída" na operação é sinônimo de retirada (baixa na própria unidade), não a
 * perna de saída de uma transferência. Mantendo os dois nomes, quem lê o razão
 * confunde `SAIDA` (central → escola) com a retirada da escola.
 *
 * `SAIDA` passa a se chamar `TRANSFERENCIA`, que é o que ele sempre foi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ali_movimento DROP CONSTRAINT IF EXISTS ali_movimento_tipo_valido');

        // 'TRANSFERENCIA' tem 13 caracteres e a coluna nasceu varchar(12).
        DB::statement('ALTER TABLE ali_movimento ALTER COLUMN mov_tipo TYPE varchar(16)');

        // Renomeia o histórico antes de apertar o CHECK de novo.
        DB::table('ali_movimento')->where('mov_tipo', 'SAIDA')->update(['mov_tipo' => 'TRANSFERENCIA']);

        DB::statement("
            ALTER TABLE ali_movimento ADD CONSTRAINT ali_movimento_tipo_valido
            CHECK (mov_tipo IN ('ENTRADA', 'TRANSFERENCIA', 'RETIRADA', 'CONSUMO', 'AJUSTE', 'ESTORNO'))
        ");

        DB::statement("COMMENT ON COLUMN ali_movimento.mov_tipo IS 'ENTRADA|TRANSFERENCIA|RETIRADA|CONSUMO|AJUSTE|ESTORNO'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ali_movimento DROP CONSTRAINT IF EXISTS ali_movimento_tipo_valido');

        DB::table('ali_movimento')->where('mov_tipo', 'TRANSFERENCIA')->update(['mov_tipo' => 'SAIDA']);

        DB::statement('ALTER TABLE ali_movimento ALTER COLUMN mov_tipo TYPE varchar(12)');

        DB::statement("
            ALTER TABLE ali_movimento ADD CONSTRAINT ali_movimento_tipo_valido
            CHECK (mov_tipo IN ('ENTRADA', 'SAIDA', 'RETIRADA', 'CONSUMO', 'AJUSTE', 'ESTORNO'))
        ");
    }
};
