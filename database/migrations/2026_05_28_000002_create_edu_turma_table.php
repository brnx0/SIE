<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_turma', function (Blueprint $table) {
            $table->bigIncrements('tur_id');
            $table->unsignedBigInteger('tur_esc_id');
            $table->unsignedBigInteger('tur_anl_id');
            $table->unsignedBigInteger('tur_seg_id');
            $table->unsignedBigInteger('tur_ser_id');
            $table->string('tur_cd_inep', 20)->nullable();
            $table->string('tur_nome', 20);
            $table->string('tur_turno', 20);
            $table->smallInteger('tur_capacidade')->nullable();
            $table->string('tur_tipo_atendimento', 60)->default('NÃO SE APLICA');
            $table->string('tur_situacao', 20)->default('ABERTA');
            $table->time('tur_hora_inicio')->nullable();
            $table->time('tur_hora_fim')->nullable();
            $table->string('tur_mediacao', 30)->nullable();
            $table->string('tur_local_diferenciado', 60)->nullable();
            $table->boolean('tur_fl_especial')->default(false);
            $table->json('tur_dias_funcionamento')->nullable();
            $table->text('tur_obs')->nullable();
            $table->timestamp('tur_created_at')->nullable();
            $table->timestamp('tur_updated_at')->nullable();
            $table->timestamp('tur_deleted_at')->nullable();

            $table->foreign('tur_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('tur_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('tur_seg_id')->references('seg_id')->on('edu_segmento')->restrictOnDelete();
            $table->foreign('tur_ser_id')->references('ser_id')->on('edu_serie')->restrictOnDelete();
        });

        // Índice único parcial (ignora soft-deleted)
        DB::statement('
            CREATE UNIQUE INDEX turma_unica ON edu_turma
            (tur_esc_id, tur_anl_id, tur_ser_id, tur_turno, tur_nome)
            WHERE tur_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_turma');
    }
};
