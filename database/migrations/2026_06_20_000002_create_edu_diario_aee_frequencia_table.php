<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Frequência do AEE — separada da frequência da turma regular.
 * Chamada por DIA de atendimento (sem tempos/disciplina): 1 presença por
 * (turma AEE, aluno, data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_aee_frequencia', function (Blueprint $t) {
            $t->bigIncrements('afr_id');
            $t->unsignedBigInteger('afr_user_id')->nullable();
            $t->unsignedBigInteger('afr_esc_id');
            $t->unsignedBigInteger('afr_anl_id');
            $t->unsignedBigInteger('afr_tur_id');
            $t->unsignedBigInteger('afr_uni_id');
            $t->unsignedBigInteger('afr_aln_id');
            $t->date('afr_dt');
            $t->boolean('afr_fl_presente')->default(true);
            $t->timestamp('afr_created_at')->nullable();
            $t->timestamp('afr_updated_at')->nullable();
            $t->timestamp('afr_deleted_at')->nullable();

            $t->foreign('afr_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('afr_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $t->foreign('afr_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();

            $t->index('afr_tur_id', 'edu_afr_tur_idx');
        });

        // 1 presença por (turma, aluno, data).
        DB::statement('
            CREATE UNIQUE INDEX edu_afr_unico ON edu_diario_aee_frequencia
            (afr_tur_id, afr_aln_id, afr_dt)
            WHERE afr_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_aee_frequencia');
    }
};
