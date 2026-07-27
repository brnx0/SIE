<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Censo Educacenso — reg 20:
 *  · campo 22: Turma de Educação Especial (classe especial).
 *  · campo 29 (obrigatório): Turma de Formação por Alternância
 *    (tempo-escola e tempo-comunidade). Não se aplica à Educação Infantil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma', function (Blueprint $table) {
            $table->boolean('tur_fl_classe_especial')->default(false)->after('tur_local_diferenciado');
            $table->boolean('tur_fl_alternancia')->default(false)->after('tur_fl_classe_especial');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma', function (Blueprint $table) {
            $table->dropColumn(['tur_fl_classe_especial', 'tur_fl_alternancia']);
        });
    }
};
