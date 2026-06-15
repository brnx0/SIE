<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->text('dpa_objetivos_complementares')->nullable()->after('dpa_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->dropColumn('dpa_objetivos_complementares');
        });
    }
};
