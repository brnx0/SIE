<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag: o motivo abona (justifica) a falta ou não.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_motivo_baixa_frequencia', function (Blueprint $t) {
            $t->boolean('mbf_fl_abona')->default(true)->after('mbf_descricao');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_motivo_baixa_frequencia', function (Blueprint $t) {
            $t->dropColumn('mbf_fl_abona');
        });
    }
};
