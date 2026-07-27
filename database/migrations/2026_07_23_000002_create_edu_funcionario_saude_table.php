<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quadro de deficiências do funcionário (Censo reg 30, campo 17 e itens).
 * Espelha o subconjunto de `edu_aluno_saude` referente a Tipo de Deficiência,
 * Transtorno Global do Desenvolvimento e Altas Habilidades/Superdotação.
 * Relação 1-1 com `edu_funcionario`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_funcionario_saude', function (Blueprint $t) {
            $t->bigIncrements('fus_id');
            $t->unsignedBigInteger('fus_fun_id');
            $t->boolean('fus_fl_pcd')->default(false);
            $t->jsonb('fus_deficiencias')->nullable();
            $t->jsonb('fus_transtornos_globais')->nullable();
            $t->jsonb('fus_transtornos_aprendizagem')->nullable();
            $t->text('fus_deficiencia_outro')->nullable();
            $t->boolean('fus_fl_altas_habilidades')->default(false);
            $t->string('fus_cid', 20)->nullable();
            $t->text('fus_observacao')->nullable();
            $t->timestamp('fus_created_at')->nullable();
            $t->timestamp('fus_updated_at')->nullable();

            $t->foreign('fus_fun_id')
                ->references('fun_id')
                ->on('edu_funcionario')
                ->cascadeOnDelete();

            $t->unique('fus_fun_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_funcionario_saude');
    }
};
