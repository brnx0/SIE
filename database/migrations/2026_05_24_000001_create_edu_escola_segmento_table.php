<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_escola_segmento', function (Blueprint $t) {
            $t->bigIncrements('esg_id');

            $t->unsignedBigInteger('esc_id');
            $t->foreign('esc_id')->references('esc_id')->on('edu_escola');

            $t->unsignedBigInteger('seg_id');
            $t->foreign('seg_id')->references('seg_id')->on('edu_segmento');

            // Vigência: a partir de anl_id_inicio até anl_id_fim (null = sem encerramento)
            $t->unsignedBigInteger('anl_id_inicio');
            $t->foreign('anl_id_inicio')->references('anl_id')->on('cfg_ano_letivo');

            $t->unsignedBigInteger('anl_id_fim')->nullable();
            $t->foreign('anl_id_fim')->references('anl_id')->on('cfg_ano_letivo');

            // Série inicial e final ofertadas neste segmento na escola
            $t->unsignedBigInteger('ser_id_inicio');
            $t->foreign('ser_id_inicio')->references('ser_id')->on('edu_serie');

            $t->unsignedBigInteger('ser_id_fim');
            $t->foreign('ser_id_fim')->references('ser_id')->on('edu_serie');

            $t->boolean('esg_fl_ativo')->default(true);
            $t->text('esg_motivo')->nullable();

            $t->timestamp('esg_created_at')->nullable();
            $t->timestamp('esg_updated_at')->nullable();
            $t->softDeletes('esg_deleted_at');

            $t->index(['esc_id', 'seg_id']);
            $t->index(['esc_id', 'anl_id_inicio', 'anl_id_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_escola_segmento');
    }
};
