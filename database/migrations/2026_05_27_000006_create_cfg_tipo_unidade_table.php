<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_tipo_unidade', function (Blueprint $table) {
            $table->bigIncrements('tun_id');
            $table->string('tun_tipo', 20);
            $table->unsignedBigInteger('tun_anl_id_inicio');
            $table->unsignedBigInteger('tun_anl_id_fim')->nullable();
            $table->timestamp('tun_created_at')->nullable();
            $table->timestamp('tun_updated_at')->nullable();

            $table->foreign('tun_anl_id_inicio')
                ->references('anl_id')->on('cfg_ano_letivo')
                ->restrictOnDelete();

            $table->foreign('tun_anl_id_fim')
                ->references('anl_id')->on('cfg_ano_letivo')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_tipo_unidade');
    }
};
