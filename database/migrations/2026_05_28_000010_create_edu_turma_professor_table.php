<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma_professor', function (Blueprint $table) {
            $table->bigIncrements('tup_id');
            $table->unsignedBigInteger('tup_tur_id');
            $table->unsignedBigInteger('tup_fun_id');
            $table->unsignedBigInteger('tup_dis_id');
            $table->timestamp('tup_created_at')->nullable();
            $table->timestamp('tup_updated_at')->nullable();
            $table->timestamp('tup_deleted_at')->nullable();

            $table->foreign('tup_tur_id')->references('tur_id')->on('edu_turma')->cascadeOnDelete();
            $table->foreign('tup_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();
            $table->foreign('tup_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX turma_professor_unica ON edu_turma_professor
            (tup_tur_id, tup_fun_id, tup_dis_id)
            WHERE tup_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma_professor');
    }
};
