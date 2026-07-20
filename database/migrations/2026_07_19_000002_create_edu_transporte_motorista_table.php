<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_motorista', function (Blueprint $table) {
            $table->bigIncrements('mot_id');
            $table->string('mot_nome', 150);
            $table->string('mot_cpf', 11)->nullable();
            $table->date('mot_dt_nascimento')->nullable();
            $table->string('mot_telefone', 20)->nullable();
            $table->unsignedBigInteger('mot_fun_id')->nullable(); // servidor da rede (opcional)
            $table->string('mot_cnh_numero', 11);
            $table->string('mot_cnh_categoria', 2);
            $table->date('mot_cnh_dt_validade');
            $table->boolean('mot_fl_ativo')->default(true);
            $table->timestamp('mot_created_at')->nullable();
            $table->timestamp('mot_updated_at')->nullable();
            $table->softDeletesTz('mot_deleted_at');

            $table->foreign('mot_fun_id')->references('fun_id')->on('edu_funcionario');
        });

        DB::statement('CREATE UNIQUE INDEX edu_transporte_motorista_cpf_unique ON edu_transporte_motorista (mot_cpf) WHERE mot_cpf IS NOT NULL AND mot_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_motorista');
    }
};
