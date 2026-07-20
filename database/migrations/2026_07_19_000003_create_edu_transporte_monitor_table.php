<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_monitor', function (Blueprint $table) {
            $table->bigIncrements('tmo_id');
            $table->string('tmo_nome', 150);
            $table->string('tmo_cpf', 11)->nullable();
            $table->date('tmo_dt_nascimento')->nullable();
            $table->string('tmo_telefone', 20)->nullable();
            $table->unsignedBigInteger('tmo_fun_id')->nullable(); // vínculo funcional (opcional)
            $table->boolean('tmo_fl_ativo')->default(true);
            $table->timestamp('tmo_created_at')->nullable();
            $table->timestamp('tmo_updated_at')->nullable();
            $table->softDeletesTz('tmo_deleted_at');

            $table->foreign('tmo_fun_id')->references('fun_id')->on('edu_funcionario');
        });

        DB::statement('CREATE UNIQUE INDEX edu_transporte_monitor_cpf_unique ON edu_transporte_monitor (tmo_cpf) WHERE tmo_cpf IS NOT NULL AND tmo_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_monitor');
    }
};
