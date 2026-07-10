<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_curso_formacao_superior', function (Blueprint $t) {
            $t->bigIncrements('cfs_id');
            $t->string('cfs_codigo', 12)->unique();
            $t->string('cfs_nome', 255);
            $t->string('cfs_grau', 40)->nullable();
            $t->string('cfs_area_geral', 120)->nullable();
            $t->string('cfs_area_detalhada', 180)->nullable();
            $t->boolean('cfs_fl_ativo')->default(true);

            $t->timestamp('cfs_created_at')->nullable();
            $t->timestamp('cfs_updated_at')->nullable();

            $t->index('cfs_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_curso_formacao_superior');
    }
};
