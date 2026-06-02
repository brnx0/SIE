<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $table) {
            $table->date('anl_dt_corte')->nullable()->after('anl_dt_censo');
        });

        // Preenche registros existentes com anl_dt_fim como fallback
        DB::statement('UPDATE cfg_ano_letivo SET anl_dt_corte = anl_dt_fim WHERE anl_dt_corte IS NULL');

        Schema::table('cfg_ano_letivo', function (Blueprint $table) {
            $table->date('anl_dt_corte')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $table) {
            $table->dropColumn('anl_dt_corte');
        });
    }
};
