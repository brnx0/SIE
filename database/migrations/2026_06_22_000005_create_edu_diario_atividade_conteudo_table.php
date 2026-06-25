<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo/metodologia do dia das turmas de ATIVIDADE.
 * Sem disciplina e sem plano (atividade não tem planejamento) → 1 registro por (turma, dia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_atividade_conteudo', function (Blueprint $table) {
            $table->bigIncrements('dtc_id');
            $table->unsignedBigInteger('dtc_user_id')->nullable();
            $table->unsignedBigInteger('dtc_tur_id');
            $table->unsignedBigInteger('dtc_uni_id');
            $table->date('dtc_dt');
            $table->text('dtc_conteudo')->nullable();
            $table->text('dtc_metodologia')->nullable();
            $table->timestamp('dtc_created_at')->nullable();
            $table->timestamp('dtc_updated_at')->nullable();
            $table->softDeletes('dtc_deleted_at');

            $table->foreign('dtc_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('dtc_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $table->foreign('dtc_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX edu_dtc_unico ON edu_diario_atividade_conteudo (dtc_tur_id, dtc_dt) WHERE dtc_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_atividade_conteudo');
    }
};
