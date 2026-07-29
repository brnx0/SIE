<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Novo vocabulário do turno da escola:
 *  · Parcial  = apenas turmas parciais (matutino, vespertino…)
 *  · Integral = apenas turmas integrais
 *  · Mista    = turmas integrais e parciais
 *
 * Valores antigos (Matutino/Vespertino/Noturno) viram "Parcial"; "Integral"
 * permanece. Sem "Mista" no legado — fica a cargo do usuário reclassificar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('edu_escola')
            ->whereIn('esc_turno_escolar', ['Matutino', 'Vespertino', 'Noturno'])
            ->update(['esc_turno_escolar' => 'Parcial']);
    }

    public function down(): void
    {
        // Irreversível: a granularidade original (matutino/vespertino/noturno)
        // não é recuperável a partir de "Parcial".
    }
};
