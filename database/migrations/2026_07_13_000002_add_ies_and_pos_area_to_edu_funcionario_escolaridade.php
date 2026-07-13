<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario_escolaridade', function (Blueprint $t) {
            // Instituição de ensino superior — níveis de graduação e pós (5,6,7,8,10).
            $t->unsignedBigInteger('fes_ies_id')->nullable()->after('fes_cfs_id');
            // Área de pós-graduação (código INEP) — níveis 7 e 8. Substitui o curso nesses níveis.
            $t->unsignedSmallInteger('fes_pos_area')->nullable()->after('fes_ies_id');

            $t->foreign('fes_ies_id')
                ->references('ies_id')
                ->on('edu_instituicao_ensino_superior')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario_escolaridade', function (Blueprint $t) {
            $t->dropForeign(['fes_ies_id']);
            $t->dropColumn(['fes_ies_id', 'fes_pos_area']);
        });
    }
};
