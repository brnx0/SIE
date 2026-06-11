<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_serie_indicador', function (Blueprint $table) {
            $table->unsignedBigInteger('ind_dis_id')->nullable()->after('ind_ser_id');
            $table->foreign('ind_dis_id')->references('dis_id')->on('edu_disciplina');
            $table->index('ind_dis_id', 'edu_serie_indicador_dis_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_serie_indicador', function (Blueprint $table) {
            $table->dropForeign(['ind_dis_id']);
            $table->dropIndex('edu_serie_indicador_dis_idx');
            $table->dropColumn('ind_dis_id');
        });
    }
};
