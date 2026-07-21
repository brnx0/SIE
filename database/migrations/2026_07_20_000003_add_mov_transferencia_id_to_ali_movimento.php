<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrupador da transferência (guia de remessa).
 *
 * Uma transferência do central para a escola gera vários pares de movimentos
 * (SAIDA + ENTRADA por lote), sem nada que os una como um único envio. Este UUID
 * é carimbado em todos os movimentos da mesma transferência, para emitir a guia de
 * remessa em PDF com exatamente os itens daquele envio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_movimento', function (Blueprint $table) {
            $table->uuid('mov_transferencia_id')->nullable()->after('mov_trf_par_id');
            $table->index('mov_transferencia_id', 'ali_movimento_transferencia_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ali_movimento', function (Blueprint $table) {
            $table->dropIndex('ali_movimento_transferencia_idx');
            $table->dropColumn('mov_transferencia_id');
        });
    }
};
