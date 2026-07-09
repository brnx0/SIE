<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno_saude', function (Blueprint $table) {
            $table->boolean('als_fl_necessita_adi')->default(false)->after('als_fl_altas_habilidades');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno_saude', function (Blueprint $table) {
            $table->dropColumn('als_fl_necessita_adi');
        });
    }
};
