<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_diario', function (Blueprint $table) {
            $table->bigIncrements('tdb_id');
            $table->unsignedBigInteger('tdb_rot_id');
            $table->date('tdb_dt_viagem'); // um registro cobre o dia inteiro (D6)
            $table->unsignedBigInteger('tdb_vei_id'); // default = titular da rota (veículo reserva editável)
            $table->unsignedBigInteger('tdb_fun_id_motorista'); // idem (substituição)
            $table->unsignedBigInteger('tdb_fun_id_monitor')->nullable();
            $table->time('tdb_hr_saida')->nullable();
            $table->time('tdb_hr_chegada')->nullable();
            $table->decimal('tdb_km_inicial', 10, 1)->nullable();
            $table->decimal('tdb_km_final', 10, 1)->nullable();
            $table->text('tdb_obs')->nullable();
            $table->boolean('tdb_fl_fechado')->default(false);
            $table->timestamp('tdb_created_at')->nullable();
            $table->timestamp('tdb_updated_at')->nullable();
            $table->softDeletesTz('tdb_deleted_at');

            $table->foreign('tdb_rot_id')->references('rot_id')->on('edu_transporte_rota');
            $table->foreign('tdb_vei_id')->references('vei_id')->on('edu_transporte_veiculo');
            $table->foreign('tdb_fun_id_motorista')->references('fun_id')->on('edu_funcionario');
            $table->foreign('tdb_fun_id_monitor')->references('fun_id')->on('edu_funcionario');
            $table->index('tdb_rot_id');
            $table->index('tdb_dt_viagem');
        });

        DB::statement('CREATE UNIQUE INDEX edu_transporte_diario_viagem_unique ON edu_transporte_diario (tdb_rot_id, tdb_dt_viagem) WHERE tdb_deleted_at IS NULL');
        DB::statement('ALTER TABLE edu_transporte_diario ADD CONSTRAINT edu_transporte_diario_km_check CHECK (tdb_km_final IS NULL OR tdb_km_inicial IS NULL OR tdb_km_final >= tdb_km_inicial)');
        DB::statement('ALTER TABLE edu_transporte_diario ADD CONSTRAINT edu_transporte_diario_hr_check CHECK (tdb_hr_chegada IS NULL OR tdb_hr_saida IS NULL OR tdb_hr_chegada >= tdb_hr_saida)');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_diario');
    }
};
