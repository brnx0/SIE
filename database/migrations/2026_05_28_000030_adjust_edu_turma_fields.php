<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma', function (Blueprint $table) {
            $table->dropColumn('tur_fl_especial');
        });

        Schema::table('edu_turma', function (Blueprint $table) {
            $table->string('tur_tipo_atendimento', 60)->nullable()->default(null)->change();
            $table->smallInteger('tur_semestre')->unsigned()->default(1)->after('tur_capacidade');
            $table->smallInteger('tur_qt_expansao')->unsigned()->nullable()->default(null)->after('tur_semestre');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma', function (Blueprint $table) {
            $table->dropColumn(['tur_semestre', 'tur_qt_expansao']);
            $table->string('tur_tipo_atendimento', 60)->default('NÃO SE APLICA')->change();
            $table->boolean('tur_fl_especial')->default(false);
        });
    }
};
