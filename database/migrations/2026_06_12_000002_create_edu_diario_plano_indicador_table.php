<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_plano_indicador', function (Blueprint $table) {
            $table->bigIncrements('dpi_id');
            $table->unsignedBigInteger('dpi_dpa_id');
            $table->unsignedInteger('dpi_ind_id');
            $table->timestamp('dpi_created_at')->nullable();
            $table->timestamp('dpi_updated_at')->nullable();

            $table->foreign('dpi_dpa_id')->references('dpa_id')->on('edu_diario_plano_aula')->cascadeOnDelete();
            $table->foreign('dpi_ind_id')->references('ind_id')->on('edu_serie_indicador')->restrictOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX edu_diario_plano_indicador_unico ON edu_diario_plano_indicador
            (dpi_dpa_id, dpi_ind_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_plano_indicador');
    }
};
