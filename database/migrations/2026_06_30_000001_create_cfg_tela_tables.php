<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_tela', function (Blueprint $t) {
            $t->bigIncrements('tla_id');
            $t->string('tla_rota', 191);
            $t->string('tla_nome', 120);
            $t->string('tla_grupo', 60)->nullable();
            $t->smallInteger('tla_ordem')->default(0);
            $t->timestamp('tla_created_at')->nullable();
            $t->timestamp('tla_updated_at')->nullable();

            $t->unique('tla_rota');
        });

        Schema::create('cfg_tela_permissao', function (Blueprint $t) {
            $t->bigIncrements('tlp_id');
            $t->unsignedBigInteger('tlp_tla_id');
            $t->string('tlp_role', 60);
            $t->smallInteger('tlp_nivel')->default(0); // 0=sem acesso, 1=ver+editar, 2=só visualizar
            $t->timestamp('tlp_created_at')->nullable();
            $t->timestamp('tlp_updated_at')->nullable();

            $t->foreign('tlp_tla_id')->references('tla_id')->on('cfg_tela')->cascadeOnDelete();
            $t->unique(['tlp_tla_id', 'tlp_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_tela_permissao');
        Schema::dropIfExists('cfg_tela');
    }
};
