<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parecer inicial do aluno — registrado ANTES dos pareceres descritivos das
 * unidades e SEM vínculo com unidade letiva. Tabela própria (e não uni_id nulo em
 * edu_diario_avaliacao_descritiva) para manter os dois registros independentes.
 * Granularidade segue a série (ser_tipo_avaliacao_descritiva), como o descritivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_parecer_inicial', function (Blueprint $table) {
            $table->bigIncrements('dpr_id');
            $table->unsignedBigInteger('dpr_user_id'); // professor autor (users.id)
            $table->unsignedBigInteger('dpr_esc_id');
            $table->unsignedBigInteger('dpr_anl_id');
            $table->unsignedBigInteger('dpr_tur_id');
            $table->unsignedBigInteger('dpr_dis_id')->nullable(); // null quando a série avalia "por aluno"
            $table->unsignedBigInteger('dpr_aln_id');
            $table->text('dpr_descricao')->nullable();
            $table->timestamp('dpr_created_at')->nullable();
            $table->timestamp('dpr_updated_at')->nullable();
            $table->timestamp('dpr_deleted_at')->nullable();

            $table->foreign('dpr_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dpr_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('dpr_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('dpr_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('dpr_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $table->foreign('dpr_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();

            $table->index('dpr_tur_id', 'edu_dpr_tur_idx');
            $table->index('dpr_aln_id', 'edu_dpr_aln_idx');
        });

        // Série "por disciplina": 1 por (turma, disciplina, aluno).
        DB::statement('
            CREATE UNIQUE INDEX edu_dpr_unico_disc ON edu_diario_parecer_inicial
            (dpr_tur_id, dpr_dis_id, dpr_aln_id)
            WHERE dpr_dis_id IS NOT NULL AND dpr_deleted_at IS NULL
        ');

        // Série "por aluno": 1 por (turma, aluno) — disciplina nula (parecer geral).
        DB::statement('
            CREATE UNIQUE INDEX edu_dpr_unico_aluno ON edu_diario_parecer_inicial
            (dpr_tur_id, dpr_aln_id)
            WHERE dpr_dis_id IS NULL AND dpr_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_parecer_inicial');
    }
};
