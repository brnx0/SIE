<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Censo escolar deixa de ser por ano letivo e passa a ser um único registro
     * por escola, mantido e atualizado ao longo do tempo. Remove a dimensão
     * cen_anl_id (e a duplicação por ano). Os preenchimentos antigos por ano são
     * descartados (decisão de produto).
     */
    public function up(): void
    {
        DB::table('cfg_censo_escolar')->truncate();

        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            $t->dropUnique(['cen_esc_id', 'cen_anl_id']);
            $t->dropForeign(['cen_anl_id']);
            $t->dropIndex(['cen_anl_id']);
            $t->dropIndex(['cen_esc_id']);
            $t->dropColumn('cen_anl_id');

            $t->unique('cen_esc_id');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            $t->dropUnique(['cen_esc_id']);
            $t->index('cen_esc_id');

            $t->unsignedBigInteger('cen_anl_id')->nullable();
            $t->foreign('cen_anl_id')->references('anl_id')->on('cfg_ano_letivo');
            $t->index('cen_anl_id');
        });
    }
};
