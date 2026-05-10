<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_bairro', function (Blueprint $t) {
            $t->bigIncrements('bai_id');
            $t->unsignedBigInteger('bai_mun_id');
            $t->string('bai_nome', 100);
            $t->boolean('bai_fl_ativo')->default(true);
            $t->timestamp('bai_created_at')->nullable();
            $t->timestamp('bai_updated_at')->nullable();

            $t->foreign('bai_mun_id')
                ->references('mun_id')
                ->on('edu_municipio')
                ->restrictOnDelete();

            $t->unique(['bai_mun_id', 'bai_nome']);
            $t->index('bai_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_bairro');
    }
};
