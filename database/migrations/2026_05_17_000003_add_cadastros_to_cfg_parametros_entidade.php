<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->boolean('par_fl_nome_pessoa_caixa_alta')->default(true);
            $t->boolean('par_fl_nome_escola_caixa_alta')->default(true);
            $t->boolean('par_fl_alertar_homonimos')->default(false);
            $t->boolean('par_fl_alertar_acentos_nomes')->default(false);
            $t->boolean('par_fl_validar_idade_serie')->default(false);
            $t->boolean('par_fl_gerar_matricula_auto')->default(true);
            $t->boolean('par_fl_validar_carga_prof')->default(false);
            $t->boolean('par_fl_cpf_obrigatorio')->default(false);
            $t->boolean('par_fl_fardamento_obrigatorio')->default(false);
            // 'bloquear' | 'avisar'
            $t->string('par_tipo_validacao_carga', 10)->default('avisar');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_parametros_entidade', function (Blueprint $t) {
            $t->dropColumn([
                'par_fl_nome_pessoa_caixa_alta',
                'par_fl_nome_escola_caixa_alta',
                'par_fl_alertar_homonimos',
                'par_fl_alertar_acentos_nomes',
                'par_fl_validar_idade_serie',
                'par_fl_gerar_matricula_auto',
                'par_fl_validar_carga_prof',
                'par_fl_cpf_obrigatorio',
                'par_fl_fardamento_obrigatorio',
                'par_tipo_validacao_carga',
            ]);
        });
    }
};
