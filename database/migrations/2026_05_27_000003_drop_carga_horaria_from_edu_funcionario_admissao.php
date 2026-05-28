<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario_admissao', function (Blueprint $table) {
            if (Schema::hasColumn('edu_funcionario_admissao', 'adm_carga_horaria')) {
                $table->dropColumn('adm_carga_horaria');
            }
            if (Schema::hasColumn('edu_funcionario_admissao', 'adm_tipo_carga')) {
                $table->dropColumn('adm_tipo_carga');
            }
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario_admissao', function (Blueprint $table) {
            $table->unsignedSmallInteger('adm_carga_horaria')->default(0);
            $table->char('adm_tipo_carga', 1)->nullable();
        });
    }
};
