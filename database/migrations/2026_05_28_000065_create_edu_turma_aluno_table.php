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
            $table->bigIncrements('mat_id');
            $table->unsignedBigInteger('mat_aln_id');
            $table->unsignedBigInteger('mat_tur_id');
            $table->unsignedBigInteger('mat_anl_id');
            $table->smallInteger('mat_nr_ordem')->nullable();
            $table->string('mat_tipo_admissao', 40)->default('MATRICULA_NOVA');
            $table->string('mat_situacao', 20)->default('ATIVA');
            $table->date('mat_dt_matricula');
            $table->date('mat_dt_saida')->nullable();
            $table->text('mat_obs')->nullable();
            $table->timestamp('mat_created_at')->nullable();
            $table->timestamp('mat_updated_at')->nullable();
            $table->softDeletes('mat_deleted_at');

            $table->foreign('mat_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();
            $table->foreign('mat_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('mat_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
        });

        // Aluno só pode ter 1 matrícula ATIVA por ano letivo
        DB::statement("
            CREATE UNIQUE INDEX turma_aluno_ativa_por_ano
            ON edu_turma_aluno (mat_aln_id, mat_anl_id)
            WHERE mat_situacao = 'ATIVA' AND mat_deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_aluno');
    }
};
