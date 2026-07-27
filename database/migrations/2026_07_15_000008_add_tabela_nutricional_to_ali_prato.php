<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela nutricional do prato — informação da porção servida por aluno.
 *
 * Preenchimento opcional: prato sem tabela segue válido e continua calculando
 * demanda e baixa de estoque (isso vem da ficha técnica, não daqui).
 *
 * Os valores são declarados pelo nutricionista, não calculados a partir dos
 * itens: o cadastro de item não guarda composição nutricional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_prato', function (Blueprint $table) {
            $table->decimal('prt_nut_porcao', 10, 2)->nullable();      // tamanho da porção
            $table->unsignedInteger('prt_nut_porcao_umd_id')->nullable();
            $table->decimal('prt_nut_energia_kcal', 10, 2)->nullable();
            $table->decimal('prt_nut_carboidrato_g', 10, 2)->nullable();
            $table->decimal('prt_nut_acucares_g', 10, 2)->nullable();
            $table->decimal('prt_nut_proteina_g', 10, 2)->nullable();
            $table->decimal('prt_nut_gordura_total_g', 10, 2)->nullable();
            $table->decimal('prt_nut_gordura_saturada_g', 10, 2)->nullable();
            $table->decimal('prt_nut_gordura_trans_g', 10, 2)->nullable();
            $table->decimal('prt_nut_fibra_g', 10, 2)->nullable();
            $table->decimal('prt_nut_sodio_mg', 10, 2)->nullable();
            $table->text('prt_nut_observacao')->nullable();

            $table->foreign('prt_nut_porcao_umd_id')->references('umd_id')->on('ali_unidade_medida');
        });

        // Nutriente negativo não existe. Porção precisa ser > 0 quando informada.
        foreach ([
            'prt_nut_energia_kcal', 'prt_nut_carboidrato_g', 'prt_nut_acucares_g',
            'prt_nut_proteina_g', 'prt_nut_gordura_total_g', 'prt_nut_gordura_saturada_g',
            'prt_nut_gordura_trans_g', 'prt_nut_fibra_g', 'prt_nut_sodio_mg',
        ] as $coluna) {
            DB::statement("ALTER TABLE ali_prato ADD CONSTRAINT ali_prato_{$coluna}_nao_negativo CHECK ({$coluna} IS NULL OR {$coluna} >= 0)");
        }

        DB::statement('ALTER TABLE ali_prato ADD CONSTRAINT ali_prato_nut_porcao_positiva CHECK (prt_nut_porcao IS NULL OR prt_nut_porcao > 0)');
    }

    public function down(): void
    {
        Schema::table('ali_prato', function (Blueprint $table) {
            $table->dropForeign(['prt_nut_porcao_umd_id']);
            $table->dropColumn([
                'prt_nut_porcao', 'prt_nut_porcao_umd_id', 'prt_nut_energia_kcal',
                'prt_nut_carboidrato_g', 'prt_nut_acucares_g', 'prt_nut_proteina_g',
                'prt_nut_gordura_total_g', 'prt_nut_gordura_saturada_g', 'prt_nut_gordura_trans_g',
                'prt_nut_fibra_g', 'prt_nut_sodio_mg', 'prt_nut_observacao',
            ]);
        });
    }
};
