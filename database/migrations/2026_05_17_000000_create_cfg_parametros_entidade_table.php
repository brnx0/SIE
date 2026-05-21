<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_parametros_entidade', function (Blueprint $t) {
            $t->bigIncrements('par_id');

            $t->string('par_nome_entidade', 255);
            $t->string('par_msg_cab_secretaria', 255);
            $t->string('par_msg_cab_estado', 255);
            $t->string('par_endereco', 255);
            $t->unsignedBigInteger('par_mun_id')->nullable();
            $t->string('par_logomarca', 255)->nullable();
            $t->string('par_brasao', 255)->nullable();

            $t->timestamp('par_created_at')->nullable();
            $t->timestamp('par_updated_at')->nullable();

            $t->foreign('par_mun_id')->references('mun_id')->on('edu_municipio')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_parametros_entidade');
    }
};
