<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para: etapa Educacenso (código INEP) → segmento + série do SIE.
 * seg_id/ser_id ficam nulos até o admin mapear (por base, pois segmento/série
 * são cadastro do cliente). Etapas sem turma/série (ex.: 0 = atividade
 * complementar/AEE) marcam ced_fl_ignorar. Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_censo_etapa_depara', function (Blueprint $t) {
            $t->bigIncrements('ced_id');
            $t->integer('ced_codigo')->unique();     // código de etapa Educacenso
            $t->string('ced_descricao', 150);
            $t->unsignedBigInteger('ced_seg_id')->nullable();
            $t->unsignedBigInteger('ced_ser_id')->nullable();
            $t->boolean('ced_fl_ignorar')->default(false); // etapa sem turma/série no SIE
            $t->timestamp('ced_created_at')->nullable();
            $t->timestamp('ced_updated_at')->nullable();

            $t->foreign('ced_seg_id')->references('seg_id')->on('edu_segmento')->nullOnDelete();
            $t->foreign('ced_ser_id')->references('ser_id')->on('edu_serie')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_censo_etapa_depara');
    }
};
