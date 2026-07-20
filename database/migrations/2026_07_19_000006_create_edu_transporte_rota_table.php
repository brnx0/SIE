<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_rota', function (Blueprint $table) {
            $table->bigIncrements('rot_id');
            $table->unsignedBigInteger('rot_anl_id'); // rota planejada por ano letivo
            $table->string('rot_nome', 120);
            $table->string('rot_turno', 20); // alinhado ao domínio de turno de edu_turma
            $table->string('rot_zona', 10)->nullable(); // urbana|rural
            $table->unsignedBigInteger('rot_vei_id'); // veículo titular
            $table->unsignedBigInteger('rot_mot_id'); // motorista titular
            $table->unsignedBigInteger('rot_tmo_id')->nullable(); // monitor titular (opcional)
            $table->decimal('rot_km', 8, 2)->nullable(); // preenchível pelo simulador
            $table->integer('rot_tempo_min')->nullable(); // idem
            $table->jsonb('rot_trajeto')->nullable(); // GeoJSON LineString (simulador)
            $table->text('rot_obs')->nullable();
            $table->boolean('rot_fl_ativo')->default(true);
            $table->timestamp('rot_created_at')->nullable();
            $table->timestamp('rot_updated_at')->nullable();
            $table->softDeletesTz('rot_deleted_at');

            $table->foreign('rot_anl_id')->references('anl_id')->on('cfg_ano_letivo');
            $table->foreign('rot_vei_id')->references('vei_id')->on('edu_transporte_veiculo');
            $table->foreign('rot_mot_id')->references('mot_id')->on('edu_transporte_motorista');
            $table->foreign('rot_tmo_id')->references('tmo_id')->on('edu_transporte_monitor');
            $table->index('rot_anl_id');
        });

        DB::statement('CREATE UNIQUE INDEX edu_transporte_rota_nome_unique ON edu_transporte_rota (rot_anl_id, rot_nome) WHERE rot_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_rota');
    }
};
