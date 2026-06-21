<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lançamento MANUAL de média + faltas pela secretaria (escolas que fazem o
 * controle fora do sistema e só registram o resultado). 1 registro por
 * (turma, disciplina, unidade, aluno). Tem precedência sobre a média calculada
 * das avaliações nos relatórios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_nota_manual', function (Blueprint $t) {
            $t->bigIncrements('nmn_id');
            $t->unsignedBigInteger('nmn_anl_id');
            $t->unsignedBigInteger('nmn_esc_id');
            $t->unsignedBigInteger('nmn_tur_id');
            $t->unsignedBigInteger('nmn_dis_id');
            $t->unsignedBigInteger('nmn_uni_id');
            $t->unsignedBigInteger('nmn_aln_id');
            $t->string('nmn_tipo', 20);                 // 'numerica' | 'conceitual'
            $t->decimal('nmn_media', 5, 2)->nullable(); // numérica ou conceitual-faixa
            $t->unsignedBigInteger('nmn_cnc_id')->nullable(); // conceitual-conceito
            $t->smallInteger('nmn_faltas')->nullable();
            $t->unsignedBigInteger('nmn_user_id')->nullable();
            $t->timestamp('nmn_created_at')->nullable();
            $t->timestamp('nmn_updated_at')->nullable();
            $t->timestamp('nmn_deleted_at')->nullable();

            $t->foreign('nmn_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('nmn_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $t->foreign('nmn_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $t->foreign('nmn_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $t->foreign('nmn_cnc_id')->references('cnc_id')->on('cfg_conceito')->nullOnDelete();
            $t->foreign('nmn_user_id')->references('id')->on('users')->nullOnDelete();

            $t->index('nmn_tur_id', 'edu_nmn_tur_idx');
        });

        // 1 registro por (turma, disciplina, unidade, aluno).
        DB::statement('
            CREATE UNIQUE INDEX edu_nmn_unico ON edu_nota_manual
            (nmn_tur_id, nmn_dis_id, nmn_uni_id, nmn_aln_id)
            WHERE nmn_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_nota_manual');
    }
};
