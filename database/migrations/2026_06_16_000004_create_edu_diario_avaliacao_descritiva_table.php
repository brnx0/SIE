<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_avaliacao_descritiva', function (Blueprint $table) {
            $table->bigIncrements('dad_id');
            $table->unsignedBigInteger('dad_user_id'); // professor autor (users.id)
            $table->unsignedBigInteger('dad_esc_id');
            $table->unsignedBigInteger('dad_anl_id');
            $table->unsignedBigInteger('dad_tur_id');
            $table->unsignedBigInteger('dad_dis_id')->nullable(); // null quando a série avalia "por aluno"
            $table->unsignedBigInteger('dad_uni_id'); // bimestre/trimestre
            $table->unsignedBigInteger('dad_aln_id');
            $table->text('dad_descricao')->nullable();
            $table->timestamp('dad_created_at')->nullable();
            $table->timestamp('dad_updated_at')->nullable();
            $table->timestamp('dad_deleted_at')->nullable();

            $table->foreign('dad_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dad_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('dad_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('dad_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('dad_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $table->foreign('dad_uni_id')->references('uni_id')->on('cfg_unidade')->restrictOnDelete();
            $table->foreign('dad_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();

            $table->index('dad_tur_id', 'edu_dad_tur_idx');
            $table->index('dad_aln_id', 'edu_dad_aln_idx');
        });

        // Série "por disciplina": 1 por (turma, disciplina, período, aluno).
        DB::statement('
            CREATE UNIQUE INDEX edu_dad_unico_disc ON edu_diario_avaliacao_descritiva
            (dad_tur_id, dad_dis_id, dad_uni_id, dad_aln_id)
            WHERE dad_dis_id IS NOT NULL AND dad_deleted_at IS NULL
        ');

        // Série "por aluno": 1 por (turma, período, aluno) — disciplina nula (parecer geral).
        DB::statement('
            CREATE UNIQUE INDEX edu_dad_unico_aluno ON edu_diario_avaliacao_descritiva
            (dad_tur_id, dad_uni_id, dad_aln_id)
            WHERE dad_dis_id IS NULL AND dad_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_avaliacao_descritiva');
    }
};
