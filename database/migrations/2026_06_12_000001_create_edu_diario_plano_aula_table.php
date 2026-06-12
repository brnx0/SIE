<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_plano_aula', function (Blueprint $table) {
            $table->bigIncrements('dpa_id');
            $table->unsignedBigInteger('dpa_fun_id');
            $table->unsignedBigInteger('dpa_esc_id');
            $table->unsignedBigInteger('dpa_anl_id');
            $table->unsignedBigInteger('dpa_tur_id');
            $table->unsignedBigInteger('dpa_dis_id');
            $table->unsignedBigInteger('dpa_uni_id');
            $table->string('dpa_tema', 255);
            $table->date('dpa_dt_inicio');
            $table->date('dpa_dt_fim');
            $table->text('dpa_objeto_conhecimento');
            $table->text('dpa_estrategias')->nullable();
            $table->text('dpa_recursos');
            $table->text('dpa_competencias')->nullable();
            $table->text('dpa_avaliacao')->nullable();
            $table->text('dpa_obs_coordenador')->nullable();
            $table->string('dpa_status', 20)->default('pendente');
            $table->timestamp('dpa_created_at')->nullable();
            $table->timestamp('dpa_updated_at')->nullable();
            $table->timestamp('dpa_deleted_at')->nullable();

            $table->foreign('dpa_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();
            $table->foreign('dpa_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('dpa_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('dpa_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();
            $table->foreign('dpa_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $table->foreign('dpa_uni_id')->references('uni_id')->on('cfg_unidade')->restrictOnDelete();

            $table->index('dpa_fun_id', 'edu_diario_plano_aula_fun_idx');
            $table->index('dpa_tur_id', 'edu_diario_plano_aula_tur_idx');
            $table->index('dpa_status', 'edu_diario_plano_aula_status_idx');
        });

        DB::statement('
            CREATE UNIQUE INDEX edu_diario_plano_aula_unico ON edu_diario_plano_aula
            (dpa_fun_id, dpa_tur_id, dpa_dis_id, dpa_dt_inicio, dpa_dt_fim)
            WHERE dpa_deleted_at IS NULL
        ');

        DB::statement("
            ALTER TABLE edu_diario_plano_aula
            ADD CONSTRAINT edu_diario_plano_aula_status_chk
            CHECK (dpa_status IN ('pendente','aprovado','reprovado','correcao'))
        ");

        DB::statement('
            ALTER TABLE edu_diario_plano_aula
            ADD CONSTRAINT edu_diario_plano_aula_periodo_chk
            CHECK (dpa_dt_fim >= dpa_dt_inicio)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_plano_aula');
    }
};
