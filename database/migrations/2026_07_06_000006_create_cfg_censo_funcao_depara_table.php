<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para: função/cargo Educacenso (código INEP) → cargo do SIE.
 * origem = 'gestor' (reg40 col5) ou 'docente' (reg50 col7).
 * cfd_cargo_nome usado para find-or-create em edu_cargo; cfd_crg_id cacheia o id.
 * Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_censo_funcao_depara', function (Blueprint $t) {
            $t->bigIncrements('cfd_id');
            $t->string('cfd_origem', 10);            // gestor | docente
            $t->integer('cfd_codigo');
            $t->string('cfd_cargo_nome', 100);
            $t->unsignedBigInteger('cfd_crg_id')->nullable();
            $t->timestamp('cfd_created_at')->nullable();
            $t->timestamp('cfd_updated_at')->nullable();

            $t->foreign('cfd_crg_id')->references('crg_id')->on('edu_cargo')->nullOnDelete();
            $t->unique(['cfd_origem', 'cfd_codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_censo_funcao_depara');
    }
};
