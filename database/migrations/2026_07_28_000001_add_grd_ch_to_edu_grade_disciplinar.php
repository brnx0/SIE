<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade disciplinar: carga horária (CH) da disciplina na grade.
 * Nullable; quando informada, deve ser positiva (validado na aplicação).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_grade_disciplinar', function (Blueprint $table) {
            $table->integer('grd_ch')->nullable()->after('grd_nome_alternativo');
        });
    }

    public function down(): void
    {
        Schema::table('edu_grade_disciplinar', function (Blueprint $table) {
            $table->dropColumn('grd_ch');
        });
    }
};
