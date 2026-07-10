<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_funcionario_escolaridade', function (Blueprint $t) {
            $t->bigIncrements('fes_id');
            $t->unsignedBigInteger('fes_fun_id');
            $t->unsignedTinyInteger('fes_nivel');
            $t->unsignedSmallInteger('fes_ano_inicio')->nullable();
            $t->unsignedSmallInteger('fes_ano_fim')->nullable();
            $t->unsignedBigInteger('fes_cfs_id')->nullable();

            $t->timestamp('fes_created_at')->nullable();
            $t->timestamp('fes_updated_at')->nullable();

            $t->foreign('fes_fun_id')
                ->references('fun_id')
                ->on('edu_funcionario')
                ->cascadeOnDelete();

            $t->foreign('fes_cfs_id')
                ->references('cfs_id')
                ->on('edu_curso_formacao_superior')
                ->nullOnDelete();

            $t->index('fes_fun_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_funcionario_escolaridade');
    }
};
