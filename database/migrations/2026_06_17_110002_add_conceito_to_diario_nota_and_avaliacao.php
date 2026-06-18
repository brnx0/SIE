<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota pode guardar um conceito lançado direto (modo conceito).
        Schema::table('edu_diario_nota', function (Blueprint $t) {
            $t->unsignedBigInteger('nta_cnc_id')->nullable()->after('nta_aln_id');
            $t->foreign('nta_cnc_id')->references('cnc_id')->on('cfg_conceito')->restrictOnDelete();
        });

        // No modo conceito direto a avaliação não tem valor máximo.
        DB::statement('ALTER TABLE edu_diario_avaliacao ALTER COLUMN ava_valor DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('edu_diario_nota', function (Blueprint $t) {
            $t->dropForeign(['nta_cnc_id']);
            $t->dropColumn('nta_cnc_id');
        });
    }
};
