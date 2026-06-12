<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_plano_aee', function (Blueprint $table) {
            $table->bigIncrements('dae_id');
            $table->unsignedBigInteger('dae_fun_id');
            $table->unsignedBigInteger('dae_esc_id');
            $table->unsignedBigInteger('dae_anl_id');
            $table->unsignedBigInteger('dae_tur_id');
            $table->string('dae_tema', 255);
            $table->date('dae_dt_inicio');
            $table->date('dae_dt_fim');
            $table->text('dae_objetivo');
            $table->text('dae_diagnostico')->nullable();
            $table->text('dae_area_desenv')->nullable();
            $table->text('dae_metas');
            $table->text('dae_estrategias');
            $table->text('dae_recursos');
            $table->text('dae_avaliacao')->nullable();
            $table->text('dae_obs_coordenador')->nullable();
            $table->string('dae_status', 20)->default('pendente');
            $table->timestamp('dae_created_at')->nullable();
            $table->timestamp('dae_updated_at')->nullable();
            $table->timestamp('dae_deleted_at')->nullable();

            $table->foreign('dae_fun_id')->references('fun_id')->on('edu_funcionario')->restrictOnDelete();
            $table->foreign('dae_esc_id')->references('esc_id')->on('edu_escola')->restrictOnDelete();
            $table->foreign('dae_anl_id')->references('anl_id')->on('cfg_ano_letivo')->restrictOnDelete();
            $table->foreign('dae_tur_id')->references('tur_id')->on('edu_turma')->restrictOnDelete();

            $table->index('dae_fun_id', 'edu_diario_plano_aee_fun_idx');
            $table->index('dae_tur_id', 'edu_diario_plano_aee_tur_idx');
            $table->index('dae_status', 'edu_diario_plano_aee_status_idx');
        });

        DB::statement('
            CREATE UNIQUE INDEX edu_diario_plano_aee_unico ON edu_diario_plano_aee
            (dae_fun_id, dae_tur_id, dae_dt_inicio, dae_dt_fim)
            WHERE dae_deleted_at IS NULL
        ');

        DB::statement("
            ALTER TABLE edu_diario_plano_aee
            ADD CONSTRAINT edu_diario_plano_aee_status_chk
            CHECK (dae_status IN ('pendente','aprovado','reprovado','correcao'))
        ");

        DB::statement('
            ALTER TABLE edu_diario_plano_aee
            ADD CONSTRAINT edu_diario_plano_aee_periodo_chk
            CHECK (dae_dt_fim >= dae_dt_inicio)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_plano_aee');
    }
};
