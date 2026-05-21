<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_funcionario', function (Blueprint $t) {
            $t->bigIncrements('fun_id');

            // Dados pessoais
            $t->string('fun_nome', 100);
            $t->date('fun_dt_nascimento');
            $t->char('fun_sexo', 1);
            $t->unsignedTinyInteger('fun_cor_raca');        // Etnia (Censo)
            $t->string('fun_nacionalidade', 60)->default('Brasileira');
            $t->string('fun_pais_origem', 60)->default('Brasil');
            $t->unsignedBigInteger('fun_mun_id_nasc');      // Naturalidade
            $t->char('fun_cpf', 11)->unique();
            $t->string('fun_religiao', 60)->nullable();
            $t->unsignedTinyInteger('fun_escolaridade')->nullable();
            $t->unsignedTinyInteger('fun_estado_civil')->nullable();
            $t->string('fun_povo_indigena', 60)->nullable();
            $t->char('fun_cd_censo', 12)->nullable();       // Código CENSO

            // Documentação
            $t->string('fun_rg_numero', 20)->nullable();
            $t->date('fun_rg_dt_emissao')->nullable();
            $t->char('fun_rg_uf', 2)->nullable();
            $t->string('fun_rg_orgao_emissor', 20)->nullable();
            $t->string('fun_certidao_modelo', 30)->nullable();
            $t->string('fun_certidao_tipo', 30)->nullable();
            $t->date('fun_certidao_dt_emissao')->nullable();
            $t->string('fun_certidao_numero', 32)->nullable();
            $t->string('fun_certidao_livro', 10)->nullable();
            $t->string('fun_certidao_pagina', 10)->nullable();
            $t->unsignedBigInteger('fun_certidao_mun_id')->nullable();
            $t->string('fun_certidao_cartorio', 100)->nullable();
            $t->string('fun_ctps_numero', 20)->nullable();
            $t->string('fun_ctps_serie', 10)->nullable();
            $t->string('fun_pis_pasep', 20)->nullable();
            $t->string('fun_titulo_eleitor', 15)->nullable();
            $t->string('fun_titulo_zona', 5)->nullable();
            $t->string('fun_titulo_secao', 5)->nullable();
            $t->string('fun_certificado_reservista', 20)->nullable();

            // Endereço
            $t->char('fun_cep', 8)->nullable();
            $t->string('fun_logradouro', 150)->nullable();
            $t->string('fun_numero', 10)->nullable();
            $t->string('fun_complemento', 100)->nullable();
            $t->string('fun_bairro', 100)->nullable();
            $t->string('fun_cidade', 100)->nullable();
            $t->char('fun_uf', 2)->nullable();

            // Contato
            $t->char('fun_telefone', 11)->nullable();
            $t->char('fun_celular', 11)->nullable();
            $t->string('fun_email', 150)->nullable();

            $t->string('fun_foto', 255)->nullable();
            $t->boolean('fun_fl_ativo')->default(true);

            $t->timestamp('fun_created_at')->nullable();
            $t->timestamp('fun_updated_at')->nullable();
            $t->softDeletes('fun_deleted_at');

            $t->foreign('fun_mun_id_nasc')
                ->references('mun_id')
                ->on('edu_municipio')
                ->restrictOnDelete();

            $t->foreign('fun_certidao_mun_id')
                ->references('mun_id')
                ->on('edu_municipio')
                ->restrictOnDelete();

            $t->index('fun_nome');
            $t->index('fun_mun_id_nasc');
        });

        DB::statement('CREATE UNIQUE INDEX edu_funcionario_fun_cd_censo_unique ON edu_funcionario (fun_cd_censo) WHERE fun_cd_censo IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_funcionario');
    }
};
