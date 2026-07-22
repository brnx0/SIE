<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local de funcionamento da escola (Censo — registro 10, campos 3 a 8).
     * Grupo de flags 0-Não / 1-Sim indicando onde a escola funciona.
     */
    public function up(): void
    {
        Schema::table('edu_escola', function (Blueprint $table) {
            $table->boolean('esc_fl_local_predio')->default(false)->after('esc_fl_predio_compartilhado');
            $table->boolean('esc_fl_local_outra_escola')->default(false)->after('esc_fl_local_predio');
            $table->boolean('esc_fl_local_galpao')->default(false)->after('esc_fl_local_outra_escola');
            $table->boolean('esc_fl_local_socioeducativa')->default(false)->after('esc_fl_local_galpao');
            $table->boolean('esc_fl_local_prisional')->default(false)->after('esc_fl_local_socioeducativa');
            $table->boolean('esc_fl_local_outros')->default(false)->after('esc_fl_local_prisional');
        });
    }

    public function down(): void
    {
        Schema::table('edu_escola', function (Blueprint $table) {
            $table->dropColumn([
                'esc_fl_local_predio',
                'esc_fl_local_outra_escola',
                'esc_fl_local_galpao',
                'esc_fl_local_socioeducativa',
                'esc_fl_local_prisional',
                'esc_fl_local_outros',
            ]);
        });
    }
};
