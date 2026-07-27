<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visitas técnicas às unidades escolares: tipos, questionário, aplicação e ateste.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catálogos simples (nome + situação), servidos por CatalogoSimplesController.
        Schema::create('ali_tipo_visita', function (Blueprint $table) {
            $table->increments('tpv_id');
            $table->string('tpv_nome', 80);
            $table->boolean('tpv_fl_ativo')->default(true);
            $table->timestamp('tpv_created_at')->nullable();
            $table->timestamp('tpv_updated_at')->nullable();
        });

        Schema::create('ali_tipo_questionario', function (Blueprint $table) {
            $table->increments('tpq_id');
            $table->string('tpq_nome', 80);
            $table->boolean('tpq_fl_ativo')->default(true);
            $table->timestamp('tpq_created_at')->nullable();
            $table->timestamp('tpq_updated_at')->nullable();
        });

        foreach (['ali_tipo_visita' => 'tpv_nome', 'ali_tipo_questionario' => 'tpq_nome'] as $tabela => $coluna) {
            DB::statement("CREATE UNIQUE INDEX {$tabela}_{$coluna}_unique ON {$tabela} (UPPER({$coluna}))");
        }

        /* Perguntas do questionário. */
        Schema::create('ali_pergunta', function (Blueprint $table) {
            $table->increments('prg_id');
            $table->unsignedInteger('prg_tpq_id');
            $table->integer('prg_ordem')->default(1);
            $table->text('prg_texto');
            $table->string('prg_tipo_resposta', 10); // SIM_NAO|NOTA|TEXTO|MULTIPLA
            $table->boolean('prg_fl_obrigatoria')->default(true);
            $table->boolean('prg_fl_ativo')->default(true);
            $table->timestamp('prg_created_at')->nullable();
            $table->timestamp('prg_updated_at')->nullable();

            $table->foreign('prg_tpq_id')->references('tpq_id')->on('ali_tipo_questionario')->cascadeOnDelete();

            $table->index(['prg_tpq_id', 'prg_ordem'], 'ali_pergunta_questionario_ordem_idx');
        });

        DB::statement("ALTER TABLE ali_pergunta ADD CONSTRAINT ali_pergunta_tipo_valido CHECK (prg_tipo_resposta IN ('SIM_NAO', 'NOTA', 'TEXTO', 'MULTIPLA'))");

        /* Opções da pergunta de múltipla escolha. */
        Schema::create('ali_pergunta_opcao', function (Blueprint $table) {
            $table->increments('pro_id');
            $table->unsignedInteger('pro_prg_id');
            $table->integer('pro_ordem')->default(1);
            $table->string('pro_texto', 150);
            $table->decimal('pro_peso', 8, 2)->nullable();
            $table->timestamp('pro_created_at')->nullable();
            $table->timestamp('pro_updated_at')->nullable();

            $table->foreign('pro_prg_id')->references('prg_id')->on('ali_pergunta')->cascadeOnDelete();
        });

        /*
         * Visita técnica. Programação, execução e ateste são momentos distintos:
         * quem realiza (`vis_fun_id`) não é quem atesta (`vis_fun_ateste_id`).
         */
        Schema::create('ali_visita', function (Blueprint $table) {
            $table->increments('vis_id');
            $table->unsignedBigInteger('vis_esc_id');
            $table->unsignedInteger('vis_tpv_id');
            $table->unsignedInteger('vis_tpq_id')->nullable(); // questionário aplicado
            $table->unsignedBigInteger('vis_fun_id')->nullable();  // técnico responsável
            $table->unsignedBigInteger('vis_usr_id')->nullable();

            $table->date('vis_dt_programada');
            $table->date('vis_dt_realizada')->nullable();
            $table->string('vis_situacao', 12)->default('PROGRAMADA'); // PROGRAMADA|REALIZADA|CANCELADA|ATESTADA

            $table->unsignedBigInteger('vis_fun_ateste_id')->nullable();
            $table->date('vis_dt_ateste')->nullable();
            $table->text('vis_parecer')->nullable();
            $table->text('vis_obs')->nullable();
            $table->text('vis_motivo_cancelamento')->nullable();

            $table->timestamp('vis_created_at')->nullable();
            $table->timestamp('vis_updated_at')->nullable();
            $table->softDeletesTz('vis_deleted_at');

            $table->foreign('vis_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('vis_tpv_id')->references('tpv_id')->on('ali_tipo_visita');
            $table->foreign('vis_tpq_id')->references('tpq_id')->on('ali_tipo_questionario');
            $table->foreign('vis_fun_id')->references('fun_id')->on('edu_funcionario');
            $table->foreign('vis_fun_ateste_id')->references('fun_id')->on('edu_funcionario');
            $table->foreign('vis_usr_id')->references('id')->on('users');

            $table->index(['vis_esc_id', 'vis_dt_programada'], 'ali_visita_escola_dt_idx');
        });

        DB::statement("ALTER TABLE ali_visita ADD CONSTRAINT ali_visita_situacao_valida CHECK (vis_situacao IN ('PROGRAMADA', 'REALIZADA', 'CANCELADA', 'ATESTADA'))");
        // Realizada sem data de realização seria visita sem quando.
        DB::statement("ALTER TABLE ali_visita ADD CONSTRAINT ali_visita_realizada_tem_data CHECK (vis_situacao NOT IN ('REALIZADA', 'ATESTADA') OR vis_dt_realizada IS NOT NULL)");
        // Atestar exige quem atestou e quando — é o que dá validade ao ateste.
        DB::statement("ALTER TABLE ali_visita ADD CONSTRAINT ali_visita_ateste_completo CHECK (vis_situacao <> 'ATESTADA' OR (vis_fun_ateste_id IS NOT NULL AND vis_dt_ateste IS NOT NULL))");
        DB::statement("ALTER TABLE ali_visita ADD CONSTRAINT ali_visita_cancelamento_justificado CHECK (vis_situacao <> 'CANCELADA' OR vis_motivo_cancelamento IS NOT NULL)");

        /*
         * Respostas. Guardam o texto da pergunta como estava no dia: editar ou
         * inativar a pergunta depois não pode reescrever o que foi respondido —
         * é isso que mantém a visita antiga legível e auditável.
         */
        Schema::create('ali_visita_resposta', function (Blueprint $table) {
            $table->increments('vre_id');
            $table->unsignedInteger('vre_vis_id');
            $table->unsignedInteger('vre_prg_id');
            $table->unsignedInteger('vre_pro_id')->nullable(); // opção escolhida (MULTIPLA)

            $table->text('vre_pergunta_texto');          // congelado no momento da resposta
            $table->string('vre_tipo_resposta', 10);     // congelado também
            $table->text('vre_resposta')->nullable();
            $table->text('vre_obs')->nullable();

            $table->timestamp('vre_created_at')->nullable();
            $table->timestamp('vre_updated_at')->nullable();

            $table->foreign('vre_vis_id')->references('vis_id')->on('ali_visita')->cascadeOnDelete();
            $table->foreign('vre_prg_id')->references('prg_id')->on('ali_pergunta');
            $table->foreign('vre_pro_id')->references('pro_id')->on('ali_pergunta_opcao');

            $table->unique(['vre_vis_id', 'vre_prg_id'], 'ali_visita_resposta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_visita_resposta');
        Schema::dropIfExists('ali_visita');
        Schema::dropIfExists('ali_pergunta_opcao');
        Schema::dropIfExists('ali_pergunta');
        Schema::dropIfExists('ali_tipo_questionario');
        Schema::dropIfExists('ali_tipo_visita');
    }
};
