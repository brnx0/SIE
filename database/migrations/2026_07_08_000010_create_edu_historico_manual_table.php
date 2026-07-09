<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lançamento MANUAL de Histórico Escolar. Registra anos escolares que o sistema
 * NÃO calcula (escolaridade anterior / outra escola, inclusive fora da rede).
 * Só estes registros são editáveis pela tela de histórico do aluno — anos
 * derivados de matrículas/notas do sistema nunca são editáveis aqui.
 *
 * 1 cabeçalho por (aluno, ano letivo). Disciplinas em tabela filha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_historico_manual', function (Blueprint $t) {
            $t->bigIncrements('hmn_id');
            $t->unsignedBigInteger('hmn_aln_id');
            $t->unsignedBigInteger('hmn_anl_id');                 // ano letivo (rede)
            $t->unsignedBigInteger('hmn_ser_id');                 // série / ano escolar (etapa)
            $t->unsignedBigInteger('hmn_esc_id')->nullable();     // escola da rede
            $t->string('hmn_esc_nome', 180)->nullable();          // nome livre (escola fora da rede)
            $t->unsignedBigInteger('hmn_mun_id')->nullable();     // município (nossa base) p/ escola fora da rede
            $t->unsignedInteger('hmn_carga_horaria')->nullable();
            $t->unsignedInteger('hmn_dias_letivos')->nullable();
            $t->decimal('hmn_frequencia', 5, 2)->nullable();      // % frequência
            $t->smallInteger('hmn_tas_cod')->nullable();          // situação final (aprovação)
            $t->unsignedBigInteger('hmn_user_id')->nullable();
            $t->timestamp('hmn_created_at')->nullable();
            $t->timestamp('hmn_updated_at')->nullable();
            $t->timestamp('hmn_deleted_at')->nullable();

            $t->foreign('hmn_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $t->foreign('hmn_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $t->foreign('hmn_ser_id')->references('ser_id')->on('edu_serie')->restrictOnDelete();
            $t->foreign('hmn_esc_id')->references('esc_id')->on('edu_escola')->nullOnDelete();
            $t->foreign('hmn_mun_id')->references('mun_id')->on('edu_municipio')->nullOnDelete();
            $t->foreign('hmn_tas_cod')->references('tas_cod')->on('edu_turma_aluno_situacao')->nullOnDelete();
            $t->foreign('hmn_user_id')->references('id')->on('users')->nullOnDelete();

            $t->index('hmn_aln_id', 'edu_hmn_aln_idx');
        });

        // 1 lançamento manual por (aluno, ano letivo).
        DB::statement('
            CREATE UNIQUE INDEX edu_hmn_unico ON edu_historico_manual
            (hmn_aln_id, hmn_anl_id)
            WHERE hmn_deleted_at IS NULL
        ');

        Schema::create('edu_historico_manual_disciplina', function (Blueprint $t) {
            $t->bigIncrements('hmd_id');
            $t->unsignedBigInteger('hmd_hmn_id');
            $t->unsignedBigInteger('hmd_dis_id');
            $t->decimal('hmd_mf', 4, 1)->nullable();       // MF numérica
            $t->unsignedBigInteger('hmd_cnc_id')->nullable(); // MF conceitual (cfg_conceito)
            $t->unsignedSmallInteger('hmd_ordem')->default(0);
            $t->timestamp('hmd_created_at')->nullable();
            $t->timestamp('hmd_updated_at')->nullable();
            $t->timestamp('hmd_deleted_at')->nullable();

            $t->foreign('hmd_hmn_id')->references('hmn_id')->on('edu_historico_manual')->cascadeOnDelete();
            $t->foreign('hmd_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $t->foreign('hmd_cnc_id')->references('cnc_id')->on('cfg_conceito')->nullOnDelete();

            $t->index('hmd_hmn_id', 'edu_hmd_hmn_idx');
        });

        // 1 disciplina por lançamento.
        DB::statement('
            CREATE UNIQUE INDEX edu_hmd_unico ON edu_historico_manual_disciplina
            (hmd_hmn_id, hmd_dis_id)
            WHERE hmd_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_historico_manual_disciplina');
        Schema::dropIfExists('edu_historico_manual');
    }
};
