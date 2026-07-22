<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Censo — registro 10:
     *  - Campo 17: Fornece água potável.
     *  - Campos 18-23: Abastecimento de água.
     *  - Campos 24-27: Fonte de energia elétrica.
     */
    public function up(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            // SEÇÃO 30 — Água potável (campo 17)
            $t->boolean('cen_fl_agua_potavel')->default(false);

            // SEÇÃO 31 — Abastecimento de água (campos 18-23)
            $t->boolean('cen_agu_rede_publica')->default(false);
            $t->boolean('cen_agu_poco_artesiano')->default(false);
            $t->boolean('cen_agu_cacimba')->default(false);
            $t->boolean('cen_agu_fonte_rio')->default(false);
            $t->boolean('cen_agu_carro_pipa')->default(false);
            $t->boolean('cen_agu_inexistente')->default(false);

            // SEÇÃO 32 — Fonte de energia elétrica (campos 24-27)
            $t->boolean('cen_ene_rede_publica')->default(false);
            $t->boolean('cen_ene_gerador_fossil')->default(false);
            $t->boolean('cen_ene_renovavel')->default(false);
            $t->boolean('cen_ene_inexistente')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            $t->dropColumn([
                'cen_fl_agua_potavel',
                'cen_agu_rede_publica', 'cen_agu_poco_artesiano', 'cen_agu_cacimba',
                'cen_agu_fonte_rio', 'cen_agu_carro_pipa', 'cen_agu_inexistente',
                'cen_ene_rede_publica', 'cen_ene_gerador_fossil', 'cen_ene_renovavel', 'cen_ene_inexistente',
            ]);
        });
    }
};
