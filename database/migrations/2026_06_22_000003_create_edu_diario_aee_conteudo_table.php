<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo/metodologia do dia do AEE + "planejamento executado".
 * Diferente do regular: AEE não tem disciplina → 1 registro por (turma, dia).
 * O snapshot do "planejamento executado" vem do plano AEE (edu_diario_plano_aee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_aee_conteudo', function (Blueprint $table) {
            $table->bigIncrements('dac_id');
            $table->unsignedBigInteger('dac_user_id')->nullable();
            $table->unsignedBigInteger('dac_tur_id');
            $table->unsignedBigInteger('dac_uni_id');
            $table->date('dac_dt');
            $table->text('dac_conteudo')->nullable();
            $table->text('dac_metodologia')->nullable();
            $table->boolean('dac_fl_plano_executado')->default(false);
            $table->unsignedBigInteger('dac_dae_id')->nullable();
            $table->timestamp('dac_created_at')->nullable();
            $table->timestamp('dac_updated_at')->nullable();
            $table->softDeletes('dac_deleted_at');

            $table->foreign('dac_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('dac_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $table->foreign('dac_dae_id')->references('dae_id')->on('edu_diario_plano_aee')->nullOnDelete();
            $table->foreign('dac_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 1 registro por (turma, dia) — ignorando soft-deletados.
        DB::statement('CREATE UNIQUE INDEX edu_dac_unico ON edu_diario_aee_conteudo (dac_tur_id, dac_dt) WHERE dac_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_aee_conteudo');
    }
};
