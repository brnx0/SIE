<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Frequência das turmas de ATIVIDADE — chamada por dia de atendimento
 * (dias da semana da turma). Espelha edu_diario_aee_frequencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_atividade_frequencia', function (Blueprint $table) {
            $table->bigIncrements('atf_id');
            $table->unsignedBigInteger('atf_user_id')->nullable();
            $table->unsignedBigInteger('atf_esc_id');
            $table->unsignedBigInteger('atf_anl_id');
            $table->unsignedBigInteger('atf_tur_id');
            $table->unsignedBigInteger('atf_uni_id');
            $table->unsignedBigInteger('atf_aln_id');
            $table->date('atf_dt');
            $table->boolean('atf_fl_presente')->default(true);
            $table->timestamp('atf_created_at')->nullable();
            $table->timestamp('atf_updated_at')->nullable();
            $table->softDeletes('atf_deleted_at');

            $table->foreign('atf_esc_id')->references('esc_id')->on('edu_escola')->cascadeOnDelete();
            $table->foreign('atf_anl_id')->references('anl_id')->on('cfg_ano_letivo')->cascadeOnDelete();
            $table->foreign('atf_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('atf_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $table->foreign('atf_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $table->foreign('atf_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Uma presença por (turma, aluno, dia) — ignorando soft-deletados.
        DB::statement('CREATE UNIQUE INDEX edu_diario_atividade_freq_unica ON edu_diario_atividade_frequencia (atf_tur_id, atf_aln_id, atf_dt) WHERE atf_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_atividade_frequencia');
    }
};
