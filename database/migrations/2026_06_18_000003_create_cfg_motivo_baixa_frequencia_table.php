<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos de baixa frequência (justificativa de falta).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cfg_motivo_baixa_frequencia')) {
            return;
        }

        Schema::create('cfg_motivo_baixa_frequencia', function (Blueprint $t) {
            // mbf_id é o código (gerado automaticamente).
            $t->smallIncrements('mbf_id');
            $t->string('mbf_descricao', 255);
            $t->boolean('mbf_fl_ativo')->default(true);
            $t->timestamp('mbf_created_at')->nullable();
            $t->timestamp('mbf_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_motivo_baixa_frequencia');
    }
};
