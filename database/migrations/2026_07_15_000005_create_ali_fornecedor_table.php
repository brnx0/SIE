<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_fornecedor', function (Blueprint $table) {
            $table->increments('frn_id');
            $table->unsignedBigInteger('frn_mun_id')->nullable();
            $table->unsignedBigInteger('frn_bai_id')->nullable();
            // Agricultura familiar costuma ser pessoa física: aceita CNPJ ou CPF.
            $table->string('frn_cnpj', 14)->nullable();
            $table->string('frn_cpf', 11)->nullable();
            $table->string('frn_razao_social', 150);
            $table->string('frn_nome_fantasia', 150)->nullable();
            $table->boolean('frn_fl_agricultura_familiar')->default(false);
            $table->string('frn_email', 120)->nullable();
            $table->string('frn_fone', 20)->nullable();
            $table->string('frn_cep', 8)->nullable();
            $table->string('frn_logradouro', 150)->nullable();
            $table->string('frn_numero', 10)->nullable();
            $table->string('frn_complemento', 60)->nullable();
            $table->boolean('frn_fl_ativo')->default(true);
            $table->timestamp('frn_created_at')->nullable();
            $table->timestamp('frn_updated_at')->nullable();
            $table->softDeletesTz('frn_deleted_at');

            $table->foreign('frn_mun_id')->references('mun_id')->on('edu_municipio');
            $table->foreign('frn_bai_id')->references('bai_id')->on('edu_bairro');
        });

        DB::statement('CREATE UNIQUE INDEX ali_fornecedor_frn_cnpj_unique ON ali_fornecedor (frn_cnpj) WHERE frn_cnpj IS NOT NULL AND frn_deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ali_fornecedor_frn_cpf_unique ON ali_fornecedor (frn_cpf) WHERE frn_cpf IS NOT NULL AND frn_deleted_at IS NULL');
        DB::statement('ALTER TABLE ali_fornecedor ADD CONSTRAINT ali_fornecedor_documento_obrigatorio CHECK (frn_cnpj IS NOT NULL OR frn_cpf IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_fornecedor');
    }
};
