<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cfg_conceito', 'cnc_peso')) {
            Schema::table('cfg_conceito', function (Blueprint $t) {
                $t->unsignedSmallInteger('cnc_peso')->default(0)->after('cnc_limite_superior');
            });
        }

        // Peso por ordem de faixa (menor limite inferior = menor peso).
        $conceitos = DB::table('cfg_conceito')->orderBy('cnc_limite_inferior')->get();
        $peso = 1;
        foreach ($conceitos as $c) {
            DB::table('cfg_conceito')->where('cnc_id', $c->cnc_id)->update(['cnc_peso' => $peso]);
            $peso++;
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cfg_conceito', 'cnc_peso')) {
            Schema::table('cfg_conceito', function (Blueprint $t) {
                $t->dropColumn('cnc_peso');
            });
        }
    }
};
