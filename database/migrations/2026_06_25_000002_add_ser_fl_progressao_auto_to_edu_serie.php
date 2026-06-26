<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Progressão automática da série: aluno avança automaticamente no encerramento.
 * Padrão das séries até o 2º ANO (ordem no segmento <= 10) = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_serie', function (Blueprint $t) {
            $t->boolean('ser_fl_progressao_auto')->default(false)->after('ser_fl_multi');
        });

        // Séries até o 2º ANO (ordem <= 10) têm progressão automática.
        DB::table('edu_serie')->where('ser_ordem_no_segmento', '<=', 10)->update(['ser_fl_progressao_auto' => true]);
    }

    public function down(): void
    {
        Schema::table('edu_serie', function (Blueprint $t) {
            $t->dropColumn('ser_fl_progressao_auto');
        });
    }
};
