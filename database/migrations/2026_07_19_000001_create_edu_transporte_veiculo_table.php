<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_veiculo', function (Blueprint $table) {
            $table->bigIncrements('vei_id');
            $table->string('vei_placa', 8);
            $table->string('vei_renavam', 11)->nullable();
            $table->string('vei_tipo', 30); // onibus|micro_onibus|van|kombi|embarcacao|outro
            $table->integer('vei_capacidade');
            $table->string('vei_marca', 60)->nullable();
            $table->string('vei_modelo', 60)->nullable();
            $table->smallInteger('vei_ano_fabricacao')->nullable();
            $table->boolean('vei_fl_acessivel')->default(false);
            $table->boolean('vei_fl_terceirizado')->default(false);
            $table->date('vei_dt_licenciamento')->nullable();
            $table->date('vei_dt_seguro')->nullable();
            $table->date('vei_dt_vistoria')->nullable();
            $table->text('vei_obs')->nullable();
            $table->boolean('vei_fl_ativo')->default(true);
            $table->timestamp('vei_created_at')->nullable();
            $table->timestamp('vei_updated_at')->nullable();
            $table->softDeletesTz('vei_deleted_at');
        });

        // Placa única entre registros vivos (soft delete libera a placa).
        DB::statement('CREATE UNIQUE INDEX edu_transporte_veiculo_placa_unique ON edu_transporte_veiculo (vei_placa) WHERE vei_deleted_at IS NULL');
        DB::statement('ALTER TABLE edu_transporte_veiculo ADD CONSTRAINT edu_transporte_veiculo_capacidade_check CHECK (vei_capacidade > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_veiculo');
    }
};
