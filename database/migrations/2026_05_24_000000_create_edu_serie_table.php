<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_serie', function (Blueprint $t) {
            $t->bigIncrements('ser_id');

            $t->unsignedBigInteger('seg_id');
            $t->foreign('seg_id')->references('seg_id')->on('edu_segmento');

            $t->string('ser_cd_referencia', 20)->nullable();
            $t->string('ser_nome', 100);
            $t->unsignedSmallInteger('ser_carga_horaria')->nullable();
            $t->unsignedSmallInteger('ser_qt_aulas_semestrais')->nullable();
            $t->unsignedSmallInteger('ser_qt_aulas_anuais')->nullable();
            $t->unsignedTinyInteger('ser_idade');
            $t->string('ser_serie_equivalente', 100)->nullable();
            $t->integer('ser_nr_ordenacao')->default(0);
            $t->integer('ser_ordem_no_segmento');

            $t->boolean('ser_fl_ativo')->default(true);
            $t->timestamp('ser_created_at')->nullable();
            $t->timestamp('ser_updated_at')->nullable();
            $t->softDeletes('ser_deleted_at');

            $t->index('seg_id');
            $t->index('ser_ordem_no_segmento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_serie');
    }
};
