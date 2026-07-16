<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duas mudanças de regra:
 *
 * 1. A ordem de fornecimento é sempre entregue no estoque central — não há
 *    escolha de local, então `ofn_loc_id` e a tabela `ali_local_estoque` saem.
 *    O estoque escolar (fase de estoque) passa a ser identificado por `esc_id`
 *    nulo = central, sem cadastro de locais para manter.
 *
 * 2. Aditivo pode ser cancelado com justificativa. Cancelar reverte o contrato
 *    ao valor anterior, e só o último aditivo não-cancelado de cada tipo pode
 *    ser cancelado (ver AplicadorAditivo::cancelar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_ordem_fornecimento', function (Blueprint $table) {
            $table->dropForeign(['ofn_loc_id']);
            $table->dropColumn('ofn_loc_id');
        });

        Schema::dropIfExists('ali_local_estoque');

        Schema::table('ali_contrato_aditivo', function (Blueprint $table) {
            $table->boolean('adt_fl_cancelado')->default(false);
            $table->timestampTz('adt_dt_cancelamento')->nullable();
            $table->text('adt_justificativa_cancelamento')->nullable();
            $table->unsignedBigInteger('adt_usr_cancelamento_id')->nullable();

            $table->foreign('adt_usr_cancelamento_id')->references('id')->on('users');
        });

        // Cancelamento exige justificativa, data e autor — anular sem dizer por quê
        // é exatamente o que a prestação de contas cobra.
        DB::statement('
            ALTER TABLE ali_contrato_aditivo ADD CONSTRAINT ali_contrato_aditivo_cancelamento_completo CHECK (
                adt_fl_cancelado = false
                OR (adt_dt_cancelamento IS NOT NULL AND adt_justificativa_cancelamento IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::table('ali_contrato_aditivo', function (Blueprint $table) {
            $table->dropForeign(['adt_usr_cancelamento_id']);
            $table->dropColumn([
                'adt_fl_cancelado', 'adt_dt_cancelamento',
                'adt_justificativa_cancelamento', 'adt_usr_cancelamento_id',
            ]);
        });

        Schema::create('ali_local_estoque', function (Blueprint $table) {
            $table->increments('loc_id');
            $table->unsignedBigInteger('loc_esc_id')->nullable();
            $table->string('loc_tipo', 20);
            $table->string('loc_nome', 120);
            $table->boolean('loc_fl_ativo')->default(true);
            $table->timestamp('loc_created_at')->nullable();
            $table->timestamp('loc_updated_at')->nullable();

            $table->foreign('loc_esc_id')->references('esc_id')->on('edu_escola');
        });

        Schema::table('ali_ordem_fornecimento', function (Blueprint $table) {
            $table->unsignedInteger('ofn_loc_id')->nullable();
            $table->foreign('ofn_loc_id')->references('loc_id')->on('ali_local_estoque');
        });
    }
};
