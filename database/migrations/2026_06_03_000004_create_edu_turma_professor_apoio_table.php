<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma_professor_apoio', function (Blueprint $t) {
            $t->bigIncrements('tpa_id');
            $t->unsignedBigInteger('tpa_tur_id');
            $t->unsignedBigInteger('tpa_fun_id');
            $t->text('tpa_obs')->nullable();
            $t->timestamp('tpa_created_at')->nullable();
            $t->timestamp('tpa_updated_at')->nullable();

            $t->foreign('tpa_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('tpa_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();

            // Sem soft-delete na unique. Para reativar, restore manual.
            $t->unique(['tpa_tur_id', 'tpa_fun_id'], 'edu_turma_professor_apoio_tur_fun_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_professor_apoio');
    }
};
