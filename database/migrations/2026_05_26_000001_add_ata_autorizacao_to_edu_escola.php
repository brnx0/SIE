<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_escola', function (Blueprint $table) {
            $table->string('esc_resolucao_num', 50)->nullable()->after('esc_fl_ativo');
            $table->string('esc_cme_portaria_num', 50)->nullable()->after('esc_resolucao_num');
            $table->date('esc_dt_publicacao')->nullable()->after('esc_cme_portaria_num');
            $table->text('esc_fundamentacao_legal')->nullable()->after('esc_dt_publicacao');
        });
    }

    public function down(): void
    {
        Schema::table('edu_escola', function (Blueprint $table) {
            $table->dropColumn([
                'esc_resolucao_num',
                'esc_cme_portaria_num',
                'esc_dt_publicacao',
                'esc_fundamentacao_legal',
            ]);
        });
    }
};
