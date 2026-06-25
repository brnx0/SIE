<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conceito geral de aprovação do ano letivo (paralelo à média geral numérica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            $t->unsignedBigInteger('anl_cnc_id_geral')->nullable()->after('anl_media_geral');
            $t->foreign('anl_cnc_id_geral')->references('cnc_id')->on('cfg_conceito')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $t) {
            $t->dropForeign(['anl_cnc_id_geral']);
            $t->dropColumn('anl_cnc_id_geral');
        });
    }
};
