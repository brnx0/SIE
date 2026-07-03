<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recuperação FINAL (anual) por (turma, disciplina, aluno). Lançada na tela de
 * Notas e Faltas da secretaria. Vale como Média Final SE for MAIOR que a MF
 * obtida antes (mesma regra da recuperação por unidade do diário).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_recuperacao_final', function (Blueprint $t) {
            $t->bigIncrements('rcf_id');
            $t->unsignedBigInteger('rcf_anl_id');
            $t->unsignedBigInteger('rcf_esc_id');
            $t->unsignedBigInteger('rcf_tur_id');
            $t->unsignedBigInteger('rcf_dis_id');
            $t->unsignedBigInteger('rcf_aln_id');
            $t->decimal('rcf_valor', 5, 2); // nota da recuperação final (numérica)
            $t->unsignedBigInteger('rcf_user_id')->nullable();
            $t->timestamp('rcf_created_at')->nullable();
            $t->timestamp('rcf_updated_at')->nullable();
            $t->timestamp('rcf_deleted_at')->nullable();

            $t->foreign('rcf_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('rcf_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $t->foreign('rcf_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $t->foreign('rcf_user_id')->references('id')->on('users')->nullOnDelete();

            $t->index('rcf_tur_id', 'edu_rcf_tur_idx');
        });

        // 1 registro por (turma, disciplina, aluno).
        DB::statement('
            CREATE UNIQUE INDEX edu_rcf_unico ON edu_recuperacao_final
            (rcf_tur_id, rcf_dis_id, rcf_aln_id)
            WHERE rcf_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_recuperacao_final');
    }
};
