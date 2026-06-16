<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_sabado_letivo', function (Blueprint $table) {
            $table->bigIncrements('sbl_id');
            $table->unsignedBigInteger('sbl_anl_id');
            $table->date('sbl_dt_sabado');
            $table->tinyInteger('sbl_dia_semana'); // 1=Seg 2=Ter 3=Qua 4=Qui 5=Sex
            $table->unsignedBigInteger('sbl_created_by_id')->nullable();
            $table->unsignedBigInteger('sbl_updated_by_id')->nullable();
            $table->timestamp('sbl_created_at')->nullable();
            $table->timestamp('sbl_updated_at')->nullable();

            $table->unique(['sbl_anl_id', 'sbl_dt_sabado']);

            $table->foreign('sbl_anl_id')
                ->references('anl_id')->on('cfg_ano_letivo')
                ->cascadeOnDelete();

            $table->foreign('sbl_created_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('sbl_updated_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_sabado_letivo');
    }
};
