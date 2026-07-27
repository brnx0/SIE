<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controle diário da preparação e demanda de merenda.
 *
 * Preparação: o que a escola preparou no dia. Ao confirmar, os itens utilizados
 * dão baixa (CONSUMO) no estoque da escola.
 *
 * Demanda: quanto de cada item a unidade precisa no mês, calculado a partir do
 * cardápio (per capita × alunos × dias letivos do dia da semana).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_preparacao', function (Blueprint $table) {
            $table->increments('prd_id');
            $table->unsignedBigInteger('prd_esc_id');
            $table->unsignedInteger('prd_prt_id');
            $table->unsignedBigInteger('prd_fun_id')->nullable();
            $table->unsignedBigInteger('prd_usr_id')->nullable();
            $table->date('prd_dt');
            $table->string('prd_turno', 20);
            $table->string('prd_refeicao', 40);
            $table->integer('prd_qtd_servida');
            $table->string('prd_situacao', 12)->default('RASCUNHO'); // RASCUNHO|CONFIRMADA|CANCELADA
            $table->text('prd_obs')->nullable();
            $table->timestamp('prd_created_at')->nullable();
            $table->timestamp('prd_updated_at')->nullable();
            $table->softDeletesTz('prd_deleted_at');

            $table->foreign('prd_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('prd_prt_id')->references('prt_id')->on('ali_prato');
            $table->foreign('prd_fun_id')->references('fun_id')->on('edu_funcionario');
            $table->foreign('prd_usr_id')->references('id')->on('users');

            $table->index(['prd_esc_id', 'prd_dt'], 'ali_preparacao_escola_dt_idx');
        });

        DB::statement("ALTER TABLE ali_preparacao ADD CONSTRAINT ali_preparacao_situacao_valida CHECK (prd_situacao IN ('RASCUNHO', 'CONFIRMADA', 'CANCELADA'))");
        DB::statement('ALTER TABLE ali_preparacao ADD CONSTRAINT ali_preparacao_servida_positiva CHECK (prd_qtd_servida > 0)');

        // Um prato por escola/dia/turno/refeição: dois registros do mesmo slot
        // dobrariam o consumo do estoque sem ninguém notar.
        DB::statement('CREATE UNIQUE INDEX ali_preparacao_slot_unique ON ali_preparacao (prd_esc_id, prd_dt, prd_turno, prd_refeicao) WHERE prd_deleted_at IS NULL AND prd_situacao <> \'CANCELADA\'');

        Schema::create('ali_preparacao_item', function (Blueprint $table) {
            $table->increments('pdi_id');
            $table->unsignedInteger('pdi_prd_id');
            $table->unsignedInteger('pdi_itm_id');
            $table->unsignedInteger('pdi_umd_id');
            // Previsto vem da ficha técnica (per capita × servidos); utilizado é
            // o que a cozinha realmente gastou. Previsto × utilizado é o que dá
            // valor ao controle diário.
            $table->decimal('pdi_qtd_prevista', 16, 4);
            $table->decimal('pdi_qtd_utilizada', 16, 4)->default(0);
            $table->timestamp('pdi_created_at')->nullable();
            $table->timestamp('pdi_updated_at')->nullable();

            $table->foreign('pdi_prd_id')->references('prd_id')->on('ali_preparacao')->cascadeOnDelete();
            $table->foreign('pdi_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('pdi_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['pdi_prd_id', 'pdi_itm_id'], 'ali_preparacao_item_unique');
        });

        DB::statement('ALTER TABLE ali_preparacao_item ADD CONSTRAINT ali_pdi_qtds_nao_negativas CHECK (pdi_qtd_prevista >= 0 AND pdi_qtd_utilizada >= 0)');

        Schema::create('ali_demanda', function (Blueprint $table) {
            $table->increments('dmd_id');
            $table->unsignedBigInteger('dmd_esc_id');
            $table->unsignedBigInteger('dmd_anl_id');
            $table->unsignedInteger('dmd_crd_id')->nullable(); // cardápio que originou o cálculo
            $table->unsignedBigInteger('dmd_usr_id')->nullable();
            $table->smallInteger('dmd_mes');
            $table->integer('dmd_ano');
            $table->integer('dmd_dias_letivos')->default(0);
            $table->string('dmd_situacao', 12)->default('RASCUNHO'); // RASCUNHO|FECHADA
            $table->text('dmd_obs')->nullable();
            $table->timestamp('dmd_created_at')->nullable();
            $table->timestamp('dmd_updated_at')->nullable();
            $table->softDeletesTz('dmd_deleted_at');

            $table->foreign('dmd_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('dmd_anl_id')->references('anl_id')->on('cfg_ano_letivo');
            $table->foreign('dmd_crd_id')->references('crd_id')->on('ali_cardapio');
            $table->foreign('dmd_usr_id')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE ali_demanda ADD CONSTRAINT ali_demanda_situacao_valida CHECK (dmd_situacao IN ('RASCUNHO', 'FECHADA'))");
        DB::statement('ALTER TABLE ali_demanda ADD CONSTRAINT ali_demanda_mes_valido CHECK (dmd_mes BETWEEN 1 AND 12)');

        // Uma demanda por escola/ano/mês: duas dariam números conflitantes para
        // a mesma compra.
        DB::statement('CREATE UNIQUE INDEX ali_demanda_escola_periodo_unique ON ali_demanda (dmd_esc_id, dmd_ano, dmd_mes) WHERE dmd_deleted_at IS NULL');

        Schema::create('ali_demanda_item', function (Blueprint $table) {
            $table->increments('dmi_id');
            $table->unsignedInteger('dmi_dmd_id');
            $table->unsignedInteger('dmi_itm_id');
            $table->unsignedInteger('dmi_umd_id');
            // Calculada nunca é sobrescrita: é o comparativo contra o ajuste
            // manual do nutricionista que dá sentido à tela.
            $table->decimal('dmi_qtd_calculada', 16, 4);
            $table->decimal('dmi_qtd_ajustada', 16, 4)->nullable();
            $table->text('dmi_justificativa')->nullable();
            $table->timestamp('dmi_created_at')->nullable();
            $table->timestamp('dmi_updated_at')->nullable();

            $table->foreign('dmi_dmd_id')->references('dmd_id')->on('ali_demanda')->cascadeOnDelete();
            $table->foreign('dmi_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('dmi_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['dmi_dmd_id', 'dmi_itm_id'], 'ali_demanda_item_unique');
        });

        DB::statement('ALTER TABLE ali_demanda_item ADD CONSTRAINT ali_dmi_qtds_nao_negativas CHECK (dmi_qtd_calculada >= 0 AND (dmi_qtd_ajustada IS NULL OR dmi_qtd_ajustada >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_demanda_item');
        Schema::dropIfExists('ali_demanda');
        Schema::dropIfExists('ali_preparacao_item');
        Schema::dropIfExists('ali_preparacao');
    }
};
