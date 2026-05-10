<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_municipio', function (Blueprint $t) {
            $t->bigIncrements('mun_id');
            $t->char('mun_codigo_ibge', 7)->unique();
            $t->string('mun_nome', 100);
            $t->char('mun_uf', 2);
            $t->timestamp('mun_created_at')->nullable();
            $t->timestamp('mun_updated_at')->nullable();

            $t->index(['mun_uf', 'mun_nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_municipio');
    }
};
