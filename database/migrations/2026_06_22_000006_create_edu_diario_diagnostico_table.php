<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliação por DIAGNÓSTICO (turmas regulares) — por indicador da disciplina.
 * 1 registro por (turma, disciplina, unidade, aluno, indicador).
 * Opção: autonomia | apoio | nao_realiza | nao_trabalha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_diagnostico', function (Blueprint $table) {
            $table->bigIncrements('dgn_id');
            $table->unsignedBigInteger('dgn_user_id')->nullable();
            $table->unsignedBigInteger('dgn_esc_id');
            $table->unsignedBigInteger('dgn_anl_id');
            $table->unsignedBigInteger('dgn_tur_id');
            $table->unsignedBigInteger('dgn_dis_id');
            $table->unsignedBigInteger('dgn_uni_id');
            $table->unsignedBigInteger('dgn_aln_id');
            $table->unsignedBigInteger('dgn_ind_id');
            $table->string('dgn_opcao', 20);
            $table->timestamp('dgn_created_at')->nullable();
            $table->timestamp('dgn_updated_at')->nullable();
            $table->softDeletes('dgn_deleted_at');

            $table->foreign('dgn_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('dgn_dis_id')->references('dis_id')->on('edu_disciplina')->cascadeOnDelete();
            $table->foreign('dgn_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $table->foreign('dgn_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $table->foreign('dgn_ind_id')->references('ind_id')->on('edu_serie_indicador')->cascadeOnDelete();
            $table->foreign('dgn_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 1 registro por (turma, disciplina, unidade, aluno, indicador).
        DB::statement('CREATE UNIQUE INDEX edu_dgn_unico ON edu_diario_diagnostico (dgn_tur_id, dgn_dis_id, dgn_uni_id, dgn_aln_id, dgn_ind_id) WHERE dgn_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_diagnostico');
    }
};
