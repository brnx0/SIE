<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('edu_turma_atividade')) {
            return;
        }

        Schema::create('edu_turma_atividade', function (Blueprint $t) {
            $t->bigIncrements('tta_id');
            $t->unsignedBigInteger('tta_tur_id');
            $t->unsignedSmallInteger('tta_atv_id');
            $t->timestamp('tta_created_at')->nullable();
            $t->timestamp('tta_updated_at')->nullable();

            $t->foreign('tta_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('tta_atv_id')->references('atv_id')->on('cfg_atividade')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS turma_atividade_unica ON edu_turma_atividade (tta_tur_id, tta_atv_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_atividade');
    }
};
