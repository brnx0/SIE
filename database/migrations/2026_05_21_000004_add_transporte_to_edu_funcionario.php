<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->boolean('fun_fl_usa_transporte')->default(false)->after('fun_email');
            $t->string('fun_transporte_tipo', 30)->nullable()->after('fun_fl_usa_transporte');
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn(['fun_fl_usa_transporte', 'fun_transporte_tipo']);
        });
    }
};
