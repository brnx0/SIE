<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging linha-a-linha do arquivo de migração do Censo.
 * icr_campos = array bruto (todos os campos entre "|"); icr_dados = subset mapeado pela versão.
 * Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imp_censo_registro', function (Blueprint $table) {
            $table->bigIncrements('icr_id');
            $table->unsignedBigInteger('icr_icl_id');
            $table->integer('icr_linha');            // nº da linha no arquivo (1-based)
            $table->string('icr_tipo', 2);           // 00|10|20|30|40|50|60|99
            $table->json('icr_campos');              // array bruto completo (UTF-8)
            $table->json('icr_dados')->nullable();   // subset mapeado (nome => valor)
            $table->string('icr_status', 10)->default('OK'); // OK|AVISO|ERRO
            $table->text('icr_erro')->nullable();
            $table->timestamp('icr_created_at')->nullable();

            $table->foreign('icr_icl_id')->references('icl_id')->on('imp_censo_lote')->cascadeOnDelete();
            $table->index(['icr_icl_id', 'icr_tipo']);
            $table->index(['icr_icl_id', 'icr_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imp_censo_registro');
    }
};
