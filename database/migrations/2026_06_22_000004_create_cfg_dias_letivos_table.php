<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dias letivos do ano por curso (segmento). 1 registro por (segmento, ano).
 * Detalhe por mês (jan..dez) e por período (unidade) — campos independentes.
 * Os totais (mês/período) são somados no frontend (somente leitura).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_dias_letivos', function (Blueprint $table) {
            $table->bigIncrements('dlt_id');
            $table->unsignedBigInteger('dlt_anl_id');
            $table->unsignedBigInteger('dlt_seg_id');
            $table->json('dlt_meses')->nullable();     // { "1": dias, ..., "12": dias }
            $table->json('dlt_periodos')->nullable();  // { "<uni_id>": dias }
            $table->unsignedBigInteger('dlt_created_by_id')->nullable();
            $table->unsignedBigInteger('dlt_updated_by_id')->nullable();
            $table->timestamp('dlt_created_at')->nullable();
            $table->timestamp('dlt_updated_at')->nullable();

            $table->unique(['dlt_anl_id', 'dlt_seg_id']);

            $table->foreign('dlt_anl_id')->references('anl_id')->on('cfg_ano_letivo')->cascadeOnDelete();
            $table->foreign('dlt_seg_id')->references('seg_id')->on('edu_segmento')->cascadeOnDelete();
            $table->foreign('dlt_created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dlt_updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_dias_letivos');
    }
};
