<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            // 'faixa' = lança número e converte pela faixa · 'conceito' = lança o conceito direto
            $t->string('anl_conceito_modo', 10)->default('faixa')->after('anl_media_geral');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            $t->dropColumn('anl_conceito_modo');
        });
    }
};
