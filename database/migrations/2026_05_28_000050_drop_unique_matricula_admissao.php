<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario_admissao', function (Blueprint $t) {
            // Constraint composta bloqueava mesma matrícula em admissões repetidas do mesmo funcionário.
            // Regra de unicidade agora é só na validação (matrícula única entre funcionários distintos).
            $t->dropUnique(['adm_fun_id', 'adm_matricula']);
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario_admissao', function (Blueprint $t) {
            $t->unique(['adm_fun_id', 'adm_matricula']);
        });
    }
};
