<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reg 30 campo 54 do Educacenso — Localização/Zona de residência (1-Urbana; 2-Rural).
     * Campo 55 (localização diferenciada) foi ignorado por decisão de projeto.
     */
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->smallInteger('aln_zona_residencia')->nullable()->after('aln_uf');
        });
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->smallInteger('fun_zona_residencia')->nullable()->after('fun_uf');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->dropColumn('aln_zona_residencia');
        });
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn('fun_zona_residencia');
        });
    }
};
