<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma_aluno', function (Blueprint $table) {
            $table->bigIncrements('tma_id');
            $table->unsignedBigInteger('tma_aln_id');
            $table->unsignedBigInteger('tma_tur_id');
            $table->unsignedBigInteger('tma_anl_id');
            $table->smallInteger('tma_nr_ordem')->nullable();

            $table->string('tma_situacao', 20)->default('ATIVA');
            $table->date('tma_dt_matricula');
            $table->date('tma_dt_saida')->nullable();
            $table->text('tma_obs')->nullable();
            $table->timestamp('tma_created_at')->nullable();
            $table->timestamp('tma_updated_at')->nullable();
            $table->softDeletes('tma_deleted_at');

            $table->foreign('tma_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();
            $table->foreign('tma_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('tma_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
        });

        // Aluno só pode ter 1 matrícula ATIVA por ano letivo
        DB::statement("
            CREATE UNIQUE INDEX turma_aluno_ativa_por_ano
            ON edu_turma_aluno (tma_aln_id, tma_anl_id)
            WHERE tma_situacao = 'ATIVA' AND tma_deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_aluno');
    }
};
