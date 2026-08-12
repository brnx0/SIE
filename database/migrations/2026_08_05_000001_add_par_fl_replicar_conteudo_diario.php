<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga/desliga a replicação do "realizado no dia" do diário para outras turmas
 * do mesmo ano escolar. Nem toda cidade usa — nasce DESLIGADA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->boolean('par_fl_replicar_conteudo_diario')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->dropColumn('par_fl_replicar_conteudo_diario');
        });
    }
};
