<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_dia_nao_letivo', function (Blueprint $table) {
            $table->bigIncrements('dnl_id');
            $table->unsignedBigInteger('dnl_anl_id');
            $table->date('dnl_dt_dia');
            $table->string('dnl_descricao', 255);
            $table->unsignedBigInteger('dnl_created_by_id')->nullable();
            $table->unsignedBigInteger('dnl_updated_by_id')->nullable();
            $table->timestamp('dnl_created_at')->nullable();
            $table->timestamp('dnl_updated_at')->nullable();

            // Um dia não pode se repetir dentro do mesmo ano letivo
            $table->unique(['dnl_anl_id', 'dnl_dt_dia']);

            $table->foreign('dnl_anl_id')->references('anl_id')->on('cfg_ano_letivo')->cascadeOnDelete();
            $table->foreign('dnl_created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dnl_updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_dia_nao_letivo');
    }
};
