<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_instituicao_ensino_superior', function (Blueprint $t) {
            $t->bigIncrements('ies_id');
            $t->unsignedInteger('ies_codigo')->unique(); // CO_IES (INEP)
            $t->string('ies_nome', 255);
            $t->unsignedSmallInteger('ies_categoria')->nullable(); // CATEGORIA_ATUAL (INEP)
            $t->unsignedInteger('ies_municipio')->nullable();       // código IBGE do município
            $t->boolean('ies_fl_ativo')->default(true);             // situação "Ativa" (vs "Extinta")

            $t->timestamp('ies_created_at')->nullable();
            $t->timestamp('ies_updated_at')->nullable();

            $t->index('ies_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_instituicao_ensino_superior');
    }
};
