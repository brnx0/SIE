<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos (material de apoio) do plano de aula. Só consulta: não entram no PDF.
 * Arquivo no disco PRIVADO ("local" → storage/app/private) — nunca servido por
 * /storage; só pela rota autenticada que confere a permissão no plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_plano_anexo', function (Blueprint $table) {
            $table->bigIncrements('dax_id');
            $table->unsignedBigInteger('dax_dpa_id');
            $table->string('dax_nome', 180);       // nome original do arquivo
            $table->string('dax_caminho', 255);    // caminho no disco "local"
            $table->string('dax_mime', 120);
            $table->unsignedBigInteger('dax_tamanho'); // bytes
            $table->unsignedBigInteger('dax_user_id')->nullable();
            $table->timestamp('dax_created_at')->nullable();
            $table->timestamp('dax_updated_at')->nullable();

            $table->foreign('dax_dpa_id')->references('dpa_id')->on('edu_diario_plano_aula')->cascadeOnDelete();
            $table->foreign('dax_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('dax_dpa_id', 'edu_dax_dpa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_plano_anexo');
    }
};
