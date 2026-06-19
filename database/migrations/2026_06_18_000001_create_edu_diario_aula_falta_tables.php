<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Frequência (lançamento de faltas) no Diário de Classe.
 * - edu_diario_aula: 1 ocorrência de aula = (turma, tempo do quadro de horário, data).
 * - edu_diario_falta: presença/ausência por aluno naquela aula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_aula', function (Blueprint $table) {
            $table->bigIncrements('aul_id');
            $table->unsignedBigInteger('aul_user_id'); // professor que lançou
            $table->unsignedBigInteger('aul_esc_id');
            $table->unsignedBigInteger('aul_anl_id');
            $table->unsignedBigInteger('aul_tur_id');
            $table->unsignedBigInteger('aul_uni_id');
            $table->unsignedBigInteger('aul_trh_id'); // tempo (slot) do quadro de horário
            $table->unsignedBigInteger('aul_dis_id')->nullable(); // disciplina do slot
            $table->date('aul_dt');
            $table->timestamp('aul_created_at')->nullable();
            $table->timestamp('aul_updated_at')->nullable();
            $table->timestamp('aul_deleted_at')->nullable();

            $table->foreign('aul_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('aul_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('aul_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('aul_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('aul_uni_id')->references('uni_id')->on('cfg_unidade')->restrictOnDelete();
            $table->foreign('aul_trh_id')->references('trh_id')->on('edu_turma_horario')->restrictOnDelete();
            $table->foreign('aul_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();

            $table->index('aul_tur_id', 'edu_aul_tur_idx');
        });

        // 1 aula por (turma, tempo, data).
        DB::statement('
            CREATE UNIQUE INDEX edu_aul_unico ON edu_diario_aula
            (aul_tur_id, aul_trh_id, aul_dt)
            WHERE aul_deleted_at IS NULL
        ');

        Schema::create('edu_diario_falta', function (Blueprint $table) {
            $table->bigIncrements('fal_id');
            $table->unsignedBigInteger('fal_aul_id');
            $table->unsignedBigInteger('fal_aln_id');
            $table->boolean('fal_fl_presente')->default(true);
            $table->timestamp('fal_created_at')->nullable();
            $table->timestamp('fal_updated_at')->nullable();
            $table->timestamp('fal_deleted_at')->nullable();

            $table->foreign('fal_aul_id')->references('aul_id')->on('edu_diario_aula')->cascadeOnDelete();
            $table->foreign('fal_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();

            $table->index('fal_aul_id', 'edu_fal_aul_idx');
        });

        // 1 registro por (aula, aluno).
        DB::statement('
            CREATE UNIQUE INDEX edu_fal_unico ON edu_diario_falta
            (fal_aul_id, fal_aln_id)
            WHERE fal_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_falta');
        Schema::dropIfExists('edu_diario_aula');
    }
};
