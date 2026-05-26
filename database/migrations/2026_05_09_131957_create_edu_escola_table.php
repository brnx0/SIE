<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_escola', function (Blueprint $t) {
            $t->bigIncrements('esc_id');

            // Identificação
            $t->char('esc_cd_inep', 8)->unique();
            $t->char('esc_cnpj', 14)->unique();
            $t->string('esc_nome', 150);
            $t->string('esc_apelido', 100);
            $t->string('esc_cd_escola', 20)->nullable();
            $t->string('esc_logo', 255)->nullable();

            // Endereço
            $t->char('esc_cep', 8);
            $t->string('esc_logradouro', 150);
            $t->string('esc_numero', 10);
            $t->string('esc_complemento', 100)->nullable();
            $t->unsignedBigInteger('esc_bai_id');
            $t->unsignedBigInteger('esc_mun_id');
            $t->char('esc_zona', 1); // U=Urbana, R=Rural
            $t->unsignedTinyInteger('esc_localizacao_dif'); // 0..7 Censo
            $t->decimal('esc_latitude', 10, 7)->nullable();
            $t->decimal('esc_longitude', 10, 7)->nullable();
            $t->string('esc_caixa_postal', 20)->nullable();

            // Contato
            $t->char('esc_ddd', 2)->nullable();
            $t->string('esc_telefone_fixo', 11)->nullable();
            $t->string('esc_fax', 11)->nullable();
            $t->string('esc_telefone_2', 11)->nullable();
            $t->string('esc_telefone_3', 11)->nullable();
            $t->string('esc_email', 150);
            $t->string('esc_site', 200)->nullable();

            // Administrativo (Censo)
            $t->unsignedTinyInteger('esc_dep_administrativa'); // 1=Federal,2=Estadual,3=Municipal,4=Privada
            $t->unsignedTinyInteger('esc_proprietario_imovel')->nullable();
            $t->unsignedTinyInteger('esc_forma_ocupacao')->nullable();
            $t->unsignedTinyInteger('esc_situacao_func'); // 1=Atividade,2=Paralisada,3=Extinta
            $t->boolean('esc_regulamentada_conselho');
            $t->string('esc_turno_escolar', 20)->nullable();
            $t->unsignedBigInteger('esc_ger_id')->nullable();
            $t->string('esc_orgao_regional_ensino', 120)->nullable();
            $t->boolean('esc_fl_creche')->default(false);
            $t->boolean('esc_fl_predio_compartilhado')->default(false);
            $t->boolean('esc_fl_sorteio_vagas')->default(false);

            // Std
            $t->boolean('esc_fl_ativo')->default(true);
            $t->timestamp('esc_created_at')->nullable();
            $t->timestamp('esc_updated_at')->nullable();
            $t->softDeletes('esc_deleted_at');

            $t->foreign('esc_bai_id')->references('bai_id')->on('edu_bairro')->restrictOnDelete();
            $t->foreign('esc_mun_id')->references('mun_id')->on('edu_municipio')->restrictOnDelete();
            $t->foreign('esc_ger_id')->references('ger_id')->on('edu_gerencia_regional')->nullOnDelete();

            $t->index('esc_nome');
            $t->index('esc_mun_id');
            $t->index('esc_situacao_func');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_escola');
    }
};
