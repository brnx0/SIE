<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_aluno', function (Blueprint $t) {
            $t->bigIncrements('aln_id');
            $t->string('aln_nome', 100);
            $t->date('aln_dt_nascimento');
            $t->char('aln_sexo', 1);
            $t->unsignedTinyInteger('aln_cor_raca');
            $t->string('aln_pais_origem', 60)->default('Brasil');
            $t->unsignedBigInteger('aln_mun_id_nasc');

            $t->char('aln_cpf', 11)->unique();
            $t->char('aln_cd_inep', 12)->nullable();
            $t->string('aln_nr_certidao', 32)->nullable();

            $t->string('aln_filiacao_1', 100)->nullable();
            $t->string('aln_filiacao_2', 100)->nullable();

            $t->char('aln_cep', 8)->nullable();
            $t->string('aln_logradouro', 150)->nullable();
            $t->string('aln_numero', 10)->nullable();
            $t->string('aln_complemento', 100)->nullable();
            $t->string('aln_bairro', 100)->nullable();
            $t->string('aln_cidade', 100)->nullable();
            $t->char('aln_uf', 2)->nullable();

            $t->char('aln_telefone', 11)->nullable();
            $t->string('aln_email', 150)->nullable();

            $t->string('aln_foto', 255)->nullable();

            $t->boolean('aln_fl_ativo')->default(true);

            $t->timestamp('aln_created_at')->nullable();
            $t->timestamp('aln_updated_at')->nullable();
            $t->softDeletes('aln_deleted_at');

            $t->foreign('aln_mun_id_nasc')
                ->references('mun_id')
                ->on('edu_municipio')
                ->restrictOnDelete();

            $t->index('aln_nome');
            $t->index('aln_mun_id_nasc');
        });

        DB::statement('CREATE UNIQUE INDEX edu_aluno_aln_cd_inep_unique ON edu_aluno (aln_cd_inep) WHERE aln_cd_inep IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_aluno');
    }
};
