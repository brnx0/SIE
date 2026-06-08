<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('edu_turma_atendimento_aee')) {
            return;
        }

        Schema::create('edu_turma_atendimento_aee', function (Blueprint $t) {
            $t->bigIncrements('tat_id');
            $t->unsignedBigInteger('tat_tur_id');
            $t->unsignedSmallInteger('tat_ate_id');
            $t->timestamp('tat_created_at')->nullable();
            $t->timestamp('tat_updated_at')->nullable();

            $t->foreign('tat_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('tat_ate_id')->references('ate_id')->on('cfg_atendimento_aee')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS turma_atendimento_aee_unica ON edu_turma_atendimento_aee (tat_tur_id, tat_ate_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_atendimento_aee');
    }
};
