<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_serie_indicador', function (Blueprint $table) {
            $table->increments('ind_id');
            $table->unsignedBigInteger('ind_ser_id');
            $table->text('ind_descricao');
            $table->boolean('ind_fl_ativo')->default(true);
            $table->timestamp('ind_created_at')->nullable();
            $table->timestamp('ind_updated_at')->nullable();
            $table->softDeletesTz('ind_deleted_at');

            $table->foreign('ind_ser_id')->references('ser_id')->on('edu_serie');
            $table->index('ind_ser_id', 'edu_serie_indicador_ser_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_serie_indicador');
    }
};
