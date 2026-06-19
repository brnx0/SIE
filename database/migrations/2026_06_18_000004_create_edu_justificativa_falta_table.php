<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Justificativa de falta (por período): aluno + intervalo de datas + motivo.
 * Cobre as faltas do aluno no intervalo (consumo no cálculo de frequência — futuro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_justificativa_falta', function (Blueprint $table) {
            $table->bigIncrements('jfa_id');
            $table->unsignedBigInteger('jfa_anl_id');
            $table->unsignedBigInteger('jfa_esc_id');
            $table->unsignedBigInteger('jfa_tur_id');
            $table->unsignedBigInteger('jfa_aln_id');
            $table->unsignedSmallInteger('jfa_mbf_id'); // motivo (cfg_motivo_baixa_frequencia)
            $table->date('jfa_dt_inicio');
            $table->date('jfa_dt_fim');
            $table->string('jfa_observacao', 500)->nullable();
            $table->unsignedBigInteger('jfa_user_id')->nullable();
            $table->timestamp('jfa_created_at')->nullable();
            $table->timestamp('jfa_updated_at')->nullable();
            $table->timestamp('jfa_deleted_at')->nullable();

            $table->foreign('jfa_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('jfa_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('jfa_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('jfa_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();
            $table->foreign('jfa_mbf_id')->references('mbf_id')->on('cfg_motivo_baixa_frequencia')->restrictOnDelete();
            $table->foreign('jfa_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['jfa_anl_id', 'jfa_esc_id'], 'edu_jfa_anl_esc_idx');
            $table->index('jfa_aln_id', 'edu_jfa_aln_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_justificativa_falta');
    }
};
