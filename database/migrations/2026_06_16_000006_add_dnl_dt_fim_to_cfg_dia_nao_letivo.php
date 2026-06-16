<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data fim opcional para feriados prolongados (período contínuo de dias não letivos).
 * Null = dia único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_dia_nao_letivo', function (Blueprint $table) {
            $table->date('dnl_dt_fim')->nullable()->after('dnl_dt_dia');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_dia_nao_letivo', function (Blueprint $table) {
            $table->dropColumn('dnl_dt_fim');
        });
    }
};
