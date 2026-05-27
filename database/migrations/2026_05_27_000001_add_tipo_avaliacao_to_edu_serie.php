<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_serie', function (Blueprint $table) {
            $table->string('ser_tipo_avaliacao', 30)->nullable()->after('ser_fl_ativo');
            $table->string('ser_tipo_avaliacao_descritiva', 20)->nullable()->after('ser_tipo_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::table('edu_serie', function (Blueprint $table) {
            $table->dropColumn(['ser_tipo_avaliacao', 'ser_tipo_avaliacao_descritiva']);
        });
    }
};
