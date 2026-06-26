<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limite de matérias que o conselho pode aprovar no encerramento.
 * NULL = ilimitado (aprova em quantas quiser). Com valor = teto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            $t->unsignedSmallInteger('anl_qtd_materias_conselho')->nullable()->after('anl_conceito_modo');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            $t->dropColumn('anl_qtd_materias_conselho');
        });
    }
};
