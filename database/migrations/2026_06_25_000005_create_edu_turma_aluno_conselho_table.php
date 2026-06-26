<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disciplinas aprovadas pelo CONSELHO para um aluno na turma (marcadas antes
 * do encerramento). O total não pode passar de cfg_ano_letivo.anl_qtd_materias_conselho
 * (NULL = ilimitado). Se o aluno for aprovado pelo conselho, a situação final
 * vira "Apr. Conselho" ignorando a frequência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma_aluno_conselho', function (Blueprint $table) {
            $table->bigIncrements('tac_id');
            $table->unsignedBigInteger('tac_tur_id');
            $table->unsignedBigInteger('tac_aln_id');
            $table->unsignedBigInteger('tac_dis_id');
            $table->unsignedBigInteger('tac_user_id')->nullable();
            $table->timestamp('tac_created_at')->nullable();
            $table->timestamp('tac_updated_at')->nullable();

            $table->foreign('tac_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('tac_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $table->foreign('tac_dis_id')->references('dis_id')->on('edu_disciplina')->cascadeOnDelete();
            $table->foreign('tac_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['tac_tur_id', 'tac_aln_id', 'tac_dis_id'], 'edu_tac_unico');
            $table->index(['tac_tur_id', 'tac_aln_id'], 'edu_tac_tur_aln_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_aluno_conselho');
    }
};
