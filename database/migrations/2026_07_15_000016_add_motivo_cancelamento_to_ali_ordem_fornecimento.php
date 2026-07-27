<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A situação da ordem é derivada do recebimento (EMITIDA → PARCIAL → ENTREGUE).
 * O cancelamento é a única transição que parte de uma decisão de gente, e por
 * isso precisa de motivo registrado — mesmo padrão da visita técnica e do
 * aditivo de contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_ordem_fornecimento', function (Blueprint $table) {
            $table->text('ofn_motivo_cancelamento')->nullable();
        });

        DB::statement("
            ALTER TABLE ali_ordem_fornecimento ADD CONSTRAINT ali_ordem_fornecimento_cancelamento_com_motivo
            CHECK (ofn_situacao <> 'CANCELADA' OR ofn_motivo_cancelamento IS NOT NULL)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ali_ordem_fornecimento DROP CONSTRAINT IF EXISTS ali_ordem_fornecimento_cancelamento_com_motivo');

        Schema::table('ali_ordem_fornecimento', function (Blueprint $table) {
            $table->dropColumn('ofn_motivo_cancelamento');
        });
    }
};
