<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para: componente curricular Educacenso (código INEP) → disciplina do SIE.
 * cdd_dis_id fica nulo até o admin mapear (disciplina é cadastro do cliente,
 * exige área de conhecimento — não auto-criável). Turma-professor bloqueia
 * componente sem de-para. Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_censo_disciplina_depara', function (Blueprint $t) {
            $t->bigIncrements('cdd_id');
            $t->integer('cdd_codigo')->unique();
            $t->string('cdd_nome', 100);
            $t->unsignedBigInteger('cdd_dis_id')->nullable();
            $t->timestamp('cdd_created_at')->nullable();
            $t->timestamp('cdd_updated_at')->nullable();

            $t->foreign('cdd_dis_id')->references('dis_id')->on('edu_disciplina')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_censo_disciplina_depara');
    }
};
