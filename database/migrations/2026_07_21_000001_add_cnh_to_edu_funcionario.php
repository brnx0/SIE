<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->string('fun_cnh_numero', 11)->nullable()->after('fun_certificado_reservista');
            $t->string('fun_cnh_categoria', 2)->nullable()->after('fun_cnh_numero');
            $t->date('fun_cnh_dt_validade')->nullable()->after('fun_cnh_categoria');
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn(['fun_cnh_numero', 'fun_cnh_categoria', 'fun_cnh_dt_validade']);
        });
    }
};
