<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_disciplina', function (Blueprint $table) {
            $table->increments('dis_id');
            $table->unsignedInteger('arc_id');
            $table->integer('dis_cod_ref')->nullable();
            $table->string('dis_nome_mec', 100);
            $table->string('dis_nome', 100);
            $table->string('dis_sigla', 20)->nullable();
            $table->boolean('dis_fl_fundamental')->default(false);
            $table->boolean('dis_fl_medio')->default(false);
            $table->boolean('dis_fl_pedagogica')->default(false);
            $table->boolean('dis_fl_ativo')->default(true);
            $table->timestamp('dis_created_at')->nullable();
            $table->timestamp('dis_updated_at')->nullable();
            $table->softDeletesTz('dis_deleted_at');

            $table->foreign('arc_id')->references('arc_id')->on('edu_area_conhecimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_disciplina');
    }
};
