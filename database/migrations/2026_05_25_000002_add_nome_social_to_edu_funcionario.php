<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->string('fun_nome_social', 100)->nullable()->after('fun_nome');
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn('fun_nome_social');
        });
    }
};
