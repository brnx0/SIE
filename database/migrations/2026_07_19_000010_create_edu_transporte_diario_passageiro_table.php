<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_diario_passageiro', function (Blueprint $table) {
            $table->bigIncrements('tdp_id');
            $table->unsignedBigInteger('tdp_tdb_id');
            $table->unsignedBigInteger('tdp_pas_id');
            $table->boolean('tdp_fl_embarcou')->default(false);
            $table->boolean('tdp_fl_desembarcou')->default(false);
            $table->time('tdp_hr_embarque')->nullable();
            $table->time('tdp_hr_desembarque')->nullable();
            $table->string('tdp_obs', 200)->nullable();
            $table->timestamp('tdp_created_at')->nullable();
            $table->timestamp('tdp_updated_at')->nullable();

            $table->foreign('tdp_tdb_id')->references('tdb_id')->on('edu_transporte_diario');
            $table->foreign('tdp_pas_id')->references('pas_id')->on('edu_transporte_passageiro');
            $table->unique(['tdp_tdb_id', 'tdp_pas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_diario_passageiro');
    }
};
