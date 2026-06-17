<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_avaliacao', function (Blueprint $t) {
            $t->bigIncrements('ava_id');

            $t->unsignedBigInteger('ava_user_id');
            $t->unsignedBigInteger('ava_esc_id');
            $t->unsignedBigInteger('ava_anl_id');
            $t->unsignedBigInteger('ava_tur_id');
            $t->unsignedBigInteger('ava_dis_id');
            $t->unsignedBigInteger('ava_uni_id');

            $t->string('ava_tipo', 20)->default('numerica'); // numerica | conceitual
            $t->string('ava_descricao', 150);
            $t->date('ava_dt');
            $t->decimal('ava_valor', 4, 2);
            $t->boolean('ava_fl_recuperacao')->default(false);

            $t->timestamp('ava_created_at')->nullable();
            $t->timestamp('ava_updated_at')->nullable();
            $t->softDeletes('ava_deleted_at');

            $t->foreign('ava_user_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('ava_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $t->foreign('ava_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $t->foreign('ava_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $t->foreign('ava_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $t->foreign('ava_uni_id')->references('uni_id')->on('cfg_unidade')->restrictOnDelete();

            $t->index(['ava_tur_id', 'ava_dis_id', 'ava_uni_id', 'ava_tipo'], 'edu_diario_avaliacao_contexto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_avaliacao');
    }
};
