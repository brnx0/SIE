<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto configurável da Ata de Resultados Finais (bloco fixo, sem dados do aluno).
 * Cada entidade/município define a redação que sai na Ata Final; vazio = a ata
 * segue sem o bloco (comportamento atual). Ver docs/CLAUDE.md "Parâmetros".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->text('par_texto_ata_final')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->dropColumn('par_texto_ata_final');
        });
    }
};
