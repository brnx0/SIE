<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_grade_disciplinar', function (Blueprint $t) {
            $t->bigIncrements('grd_id');
            $t->unsignedBigInteger('grd_anl_id');
            $t->unsignedBigInteger('grd_seg_id');
            $t->unsignedBigInteger('grd_ser_id');
            $t->unsignedBigInteger('grd_dis_id');
            $t->unsignedSmallInteger('grd_ordem')->default(1);
            $t->string('grd_nome_alternativo', 100)->nullable();
            $t->boolean('grd_fl_ativo')->default(true);
            $t->timestamp('grd_created_at')->nullable();
            $t->timestamp('grd_updated_at')->nullable();
            $t->softDeletes('grd_deleted_at');

            $t->foreign('grd_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $t->foreign('grd_seg_id')->references('seg_id')->on('edu_segmento')->restrictOnDelete();
            $t->foreign('grd_ser_id')->references('ser_id')->on('edu_serie')->restrictOnDelete();
            $t->foreign('grd_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();

            $t->index(['grd_anl_id', 'grd_seg_id', 'grd_ser_id'], 'grd_filtro_idx');
            $t->unique(['grd_anl_id', 'grd_seg_id', 'grd_ser_id', 'grd_dis_id'], 'grd_unica_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_grade_disciplinar');
    }
};
