<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_instituicao', function (Blueprint $table) {
            $table->bigIncrements('tin_id');
            $table->string('tin_nome', 150);
            $table->unsignedBigInteger('tin_esc_id')->nullable(); // vínculo à unidade da rede municipal
            $table->string('tin_esfera', 20); // municipal|estadual|federal|privada|outra
            $table->string('tin_endereco', 200)->nullable();
            $table->unsignedBigInteger('tin_bai_id')->nullable();
            $table->unsignedBigInteger('tin_mun_id')->nullable();
            $table->string('tin_telefone', 20)->nullable();
            $table->decimal('tin_latitude', 10, 7)->nullable();
            $table->decimal('tin_longitude', 10, 7)->nullable();
            $table->boolean('tin_fl_ativo')->default(true);
            $table->timestamp('tin_created_at')->nullable();
            $table->timestamp('tin_updated_at')->nullable();
            $table->softDeletesTz('tin_deleted_at');

            $table->foreign('tin_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('tin_bai_id')->references('bai_id')->on('edu_bairro');
            $table->foreign('tin_mun_id')->references('mun_id')->on('edu_municipio');
            $table->index('tin_esc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_instituicao');
    }
};
