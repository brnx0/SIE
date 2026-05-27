<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_funcionario_lotacao', function (Blueprint $t) {
            $t->bigIncrements('lot_id');
            $t->unsignedBigInteger('lot_adm_id');
            $t->unsignedBigInteger('lot_esc_id');
            $t->unsignedBigInteger('lot_crg_id');

            $t->string('lot_vinculo', 30);
            $t->string('lot_situacao_funcional', 40)->nullable();
            $t->string('lot_criterio_acesso', 100)->nullable();
            $t->date('lot_dt_inicio');
            $t->date('lot_dt_fim')->nullable();
            $t->boolean('lot_fl_ativo')->default(true);
            $t->json('lot_funcoes_sala_aula')->nullable();

            $t->timestamp('lot_created_at')->nullable();
            $t->timestamp('lot_updated_at')->nullable();

            $t->foreign('lot_adm_id')
                ->references('adm_id')
                ->on('edu_funcionario_admissao')
                ->cascadeOnDelete();

            $t->foreign('lot_esc_id')
                ->references('esc_id')
                ->on('edu_escola')
                ->restrictOnDelete();

            $t->foreign('lot_crg_id')
                ->references('crg_id')
                ->on('edu_cargo')
                ->restrictOnDelete();

            $t->index('lot_adm_id');
            $t->index('lot_esc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_funcionario_lotacao');
    }
};
