<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_aee_avaliacao', function (Blueprint $table) {
            $table->bigIncrements('dav_id');
            $table->unsignedBigInteger('dav_user_id')->nullable();
            $table->unsignedBigInteger('dav_esc_id');
            $table->unsignedBigInteger('dav_anl_id');
            $table->unsignedBigInteger('dav_tur_id');
            $table->unsignedBigInteger('dav_uni_id');
            $table->unsignedBigInteger('dav_aln_id');
            $table->date('dav_dt');
            // HTML rico (negrito/itálico/listas). Limite de 2500 caracteres de texto
            // é validado no controller (strip_tags); a coluna comporta o HTML.
            $table->text('dav_descricao');
            $table->timestamp('dav_created_at')->nullable();
            $table->timestamp('dav_updated_at')->nullable();
            $table->softDeletes('dav_deleted_at');

            $table->index(['dav_tur_id', 'dav_uni_id']);

            $table->foreign('dav_esc_id')->references('esc_id')->on('edu_escola')->cascadeOnDelete();
            $table->foreign('dav_anl_id')->references('anl_id')->on('cfg_ano_letivo')->cascadeOnDelete();
            $table->foreign('dav_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('dav_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
            $table->foreign('dav_aln_id')->references('aln_id')->on('edu_aluno')->cascadeOnDelete();
            $table->foreign('dav_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_aee_avaliacao');
    }
};
