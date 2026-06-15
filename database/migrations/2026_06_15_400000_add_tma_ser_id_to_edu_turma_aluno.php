<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->unsignedBigInteger('tma_ser_id')->nullable()->after('tma_tur_id');
            $t->foreign('tma_ser_id')->references('ser_id')->on('edu_serie');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $t) {
            $t->dropForeign(['tma_ser_id']);
            $t->dropColumn('tma_ser_id');
        });
    }
};
