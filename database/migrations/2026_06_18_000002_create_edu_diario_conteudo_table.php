<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo e metodologia da aula — 1 por dia (turma + data + professor),
 * não por tempo. Registra o que foi dado em sala naquele dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_conteudo', function (Blueprint $table) {
            $table->bigIncrements('dco_id');
            $table->unsignedBigInteger('dco_user_id'); // professor
            $table->unsignedBigInteger('dco_tur_id');
            $table->unsignedBigInteger('dco_uni_id');
            $table->date('dco_dt');
            $table->string('dco_conteudo', 255)->nullable();
            $table->string('dco_metodologia', 255)->nullable();
            $table->timestamp('dco_created_at')->nullable();
            $table->timestamp('dco_updated_at')->nullable();
            $table->timestamp('dco_deleted_at')->nullable();

            $table->foreign('dco_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dco_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('dco_uni_id')->references('uni_id')->on('cfg_unidade')->restrictOnDelete();

            $table->index('dco_tur_id', 'edu_dco_tur_idx');
        });

        // 1 registro por (turma, data, professor).
        DB::statement('
            CREATE UNIQUE INDEX edu_dco_unico ON edu_diario_conteudo
            (dco_tur_id, dco_dt, dco_user_id)
            WHERE dco_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_conteudo');
    }
};
