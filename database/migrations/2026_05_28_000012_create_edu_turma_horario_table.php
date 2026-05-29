<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma_horario', function (Blueprint $table) {
            $table->bigIncrements('trh_id');
            $table->unsignedBigInteger('trh_tur_id');
            $table->unsignedBigInteger('trh_grh_id');
            $table->string('trh_dia', 3);
            $table->unsignedBigInteger('trh_fun_id');
            $table->unsignedBigInteger('trh_dis_id');
            $table->boolean('trh_fl_tc')->default(false);
            $table->timestamp('trh_created_at')->nullable();
            $table->timestamp('trh_updated_at')->nullable();
            $table->timestamp('trh_deleted_at')->nullable();

            $table->foreign('trh_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('trh_grh_id')->references('grh_id')->on('edu_grade_horario')->restrictOnDelete();
            $table->foreign('trh_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();
            $table->foreign('trh_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX turma_horario_unica ON edu_turma_horario
            (trh_tur_id, trh_grh_id, trh_dia)
            WHERE trh_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_horario');
    }
};
