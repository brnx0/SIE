<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_serie_indicador', function (Blueprint $table) {
            $table->unsignedBigInteger('ind_anl_id')->nullable()->after('ind_dis_id');
            $table->foreign('ind_anl_id')->references('anl_id')->on('cfg_ano_letivo');
            $table->index('ind_anl_id', 'edu_serie_indicador_anl_idx');
        });

        // Backfill: ano em exercício para registros existentes.
        $anlId = DB::table('cfg_ano_letivo')
            ->where('anl_fl_em_exercicio', true)
            ->whereNull('anl_deleted_at')
            ->value('anl_id');

        if ($anlId) {
            DB::table('edu_serie_indicador')
                ->whereNull('ind_anl_id')
                ->update(['ind_anl_id' => $anlId]);
        }
    }

    public function down(): void
    {
        Schema::table('edu_serie_indicador', function (Blueprint $table) {
            $table->dropForeign(['ind_anl_id']);
            $table->dropIndex('edu_serie_indicador_anl_idx');
            $table->dropColumn('ind_anl_id');
        });
    }
};
