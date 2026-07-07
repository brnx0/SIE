<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lote de importação do Censo Escolar (migração INEP).
 * Cada upload de arquivo .txt = 1 lote. Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imp_censo_lote', function (Blueprint $table) {
            $table->bigIncrements('icl_id');
            $table->string('icl_arquivo_nome');
            $table->string('icl_arquivo_path')->nullable();
            $table->unsignedBigInteger('icl_anl_id');          // ano letivo destino (escolhido no upload)
            $table->integer('icl_versao_layout')->nullable();  // ex.: 2025
            $table->string('icl_status', 20)->default('PENDENTE'); // PENDENTE|PARSEADO|ERRO|COMMITADO
            $table->integer('icl_total_linhas')->default(0);
            $table->integer('icl_total_erros')->default(0);
            $table->json('icl_contadores')->nullable();        // { "00": 80, "20": 1963, ... }
            $table->text('icl_mensagem')->nullable();
            $table->unsignedBigInteger('icl_created_by')->nullable();
            $table->timestamp('icl_created_at')->nullable();
            $table->timestamp('icl_updated_at')->nullable();

            $table->foreign('icl_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imp_censo_lote');
    }
};
