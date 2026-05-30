<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            // SEÇÃO 33 — Esgotamento sanitário
            $t->boolean('cen_esg_rede_publica')->default(false);
            $t->boolean('cen_esg_fossa_septica')->default(false);
            $t->boolean('cen_esg_fossa_rudimentar')->default(false);
            $t->boolean('cen_esg_inexistente')->default(false);

            // SEÇÃO 34 — Destinação do lixo
            $t->boolean('cen_lxd_coleta')->default(false);
            $t->boolean('cen_lxd_queima')->default(false);
            $t->boolean('cen_lxd_enterra')->default(false);
            $t->boolean('cen_lxd_destinacao_licenciada')->default(false);
            $t->boolean('cen_lxd_outra_area')->default(false);

            // SEÇÃO 35 — Tratamento do lixo/resíduos pela escola
            $t->boolean('cen_lxt_separacao')->default(false);
            $t->boolean('cen_lxt_reaproveitamento')->default(false);
            $t->boolean('cen_lxt_reciclagem')->default(false);
            $t->boolean('cen_lxt_nao_faz')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $t) {
            $t->dropColumn([
                'cen_esg_rede_publica',
                'cen_esg_fossa_septica',
                'cen_esg_fossa_rudimentar',
                'cen_esg_inexistente',
                'cen_lxd_coleta',
                'cen_lxd_queima',
                'cen_lxd_enterra',
                'cen_lxd_destinacao_licenciada',
                'cen_lxd_outra_area',
                'cen_lxt_separacao',
                'cen_lxt_reaproveitamento',
                'cen_lxt_reciclagem',
                'cen_lxt_nao_faz',
            ]);
        });
    }
};
