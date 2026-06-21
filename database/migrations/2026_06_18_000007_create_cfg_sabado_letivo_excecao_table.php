<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escolas que NÃO terão um determinado sábado letivo (lista de exceção).
 * Ausência de linhas = todas as escolas têm o sábado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_sabado_letivo_excecao', function (Blueprint $t) {
            $t->bigIncrements('sle_id');
            $t->unsignedBigInteger('sle_sbl_id');
            $t->unsignedBigInteger('sle_esc_id');

            $t->foreign('sle_sbl_id')->references('sbl_id')->on('cfg_sabado_letivo')->cascadeOnDelete();
            $t->foreign('sle_esc_id')->references('esc_id')->on('edu_escola')->cascadeOnDelete();

            $t->unique(['sle_sbl_id', 'sle_esc_id'], 'cfg_sle_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_sabado_letivo_excecao');
    }
};
