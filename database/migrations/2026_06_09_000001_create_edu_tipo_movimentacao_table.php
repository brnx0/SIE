<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_tipo_movimentacao', function (Blueprint $table) {
            $table->smallInteger('tmv_cod')->primary();
            $table->string('tmv_descricao', 80);
            $table->smallInteger('tmv_tas_cod_entrada')->nullable();
            $table->smallInteger('tmv_tas_cod_saida')->nullable();
            $table->boolean('tmv_fl_ativo')->default(true);
            $table->timestamp('tmv_created_at')->nullable();
            $table->timestamp('tmv_updated_at')->nullable();

            $table->foreign('tmv_tas_cod_entrada')->references('tas_cod')->on('edu_turma_aluno_situacao');
            $table->foreign('tmv_tas_cod_saida')->references('tas_cod')->on('edu_turma_aluno_situacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_tipo_movimentacao');
    }
};
