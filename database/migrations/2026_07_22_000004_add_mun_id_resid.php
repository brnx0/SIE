<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reg 30 campo 53 do Educacenso — Município de residência (código IBGE da Tabela de
     * Municípios). FK para edu_municipio; o export emite mun_codigo_ibge. Coluna de texto
     * *_cidade permanece (mantida em sincronia com o município escolhido) p/ telas legadas.
     */
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->unsignedBigInteger('aln_mun_id_resid')->nullable()->after('aln_cidade');
            $t->foreign('aln_mun_id_resid')->references('mun_id')->on('edu_municipio')->nullOnDelete();
            $t->index('aln_mun_id_resid');
        });
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->unsignedBigInteger('fun_mun_id_resid')->nullable()->after('fun_cidade');
            $t->foreign('fun_mun_id_resid')->references('mun_id')->on('edu_municipio')->nullOnDelete();
            $t->index('fun_mun_id_resid');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->dropForeign(['aln_mun_id_resid']);
            $t->dropColumn('aln_mun_id_resid');
        });
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropForeign(['fun_mun_id_resid']);
            $t->dropColumn('fun_mun_id_resid');
        });
    }
};
