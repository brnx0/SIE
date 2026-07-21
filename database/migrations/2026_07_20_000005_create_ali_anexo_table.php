<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos (PDF ou imagem) de registros da alimentação — polimórfico.
 *
 * Hoje usado por contrato e visita técnica. `anx_anexavel_type`/`anx_anexavel_id`
 * apontam para o dono; o arquivo mora no disco "public" sob `anexos/{pasta}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_anexo', function (Blueprint $table) {
            $table->bigIncrements('anx_id');
            $table->string('anx_anexavel_type', 120);
            $table->unsignedBigInteger('anx_anexavel_id');
            $table->string('anx_nome', 180);         // nome original do arquivo
            $table->string('anx_caminho', 255);      // caminho no disco "public"
            $table->string('anx_mime', 100);
            $table->unsignedBigInteger('anx_tamanho'); // bytes
            $table->unsignedBigInteger('anx_usr_id')->nullable();
            $table->timestamp('anx_created_at')->nullable();
            $table->timestamp('anx_updated_at')->nullable();

            $table->foreign('anx_usr_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['anx_anexavel_type', 'anx_anexavel_id'], 'ali_anexo_anexavel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_anexo');
    }
};
