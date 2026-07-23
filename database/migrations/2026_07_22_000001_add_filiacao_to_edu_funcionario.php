<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->string('fun_filiacao_1', 100)->nullable()->after('fun_nome_social');
            $t->char('fun_filiacao_1_tipo', 3)->nullable()->after('fun_filiacao_1');
            $t->string('fun_filiacao_2', 100)->nullable()->after('fun_filiacao_1_tipo');
            $t->char('fun_filiacao_2_tipo', 3)->nullable()->after('fun_filiacao_2');
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn(['fun_filiacao_1', 'fun_filiacao_1_tipo', 'fun_filiacao_2', 'fun_filiacao_2_tipo']);
        });
    }
};
