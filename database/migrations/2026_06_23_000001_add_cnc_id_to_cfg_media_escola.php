<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Média conceitual por escola: conceito mínimo de aprovação (paralelo à média numérica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_media_escola', function (Blueprint $t) {
            $t->unsignedBigInteger('mde_cnc_id')->nullable()->after('mde_media');
            $t->foreign('mde_cnc_id')->references('cnc_id')->on('cfg_conceito')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cfg_media_escola', function (Blueprint $t) {
            $t->dropForeign(['mde_cnc_id']);
            $t->dropColumn('mde_cnc_id');
        });
    }
};
