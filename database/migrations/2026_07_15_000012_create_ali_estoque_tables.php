<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque: lote, razão (ledger) de movimentos, saldo materializado e recebimento.
 *
 * `*_esc_id` nulo = estoque central; preenchido = estoque daquela escola. Não há
 * cadastro de locais — a entrega do fornecedor é sempre no central, e a escola
 * recebe por transferência.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Lote: é o que permite o relatório de itens próximos do vencimento e a
         * baixa por FEFO. Item não perecível usa um lote sem validade.
         */
        Schema::create('ali_lote', function (Blueprint $table) {
            $table->increments('lot_id');
            $table->unsignedInteger('lot_itm_id');
            $table->unsignedInteger('lot_mrc_id')->nullable();
            $table->string('lot_cd_lote', 40)->nullable();
            $table->date('lot_dt_fabricacao')->nullable();
            $table->date('lot_dt_validade')->nullable();
            $table->timestamp('lot_created_at')->nullable();
            $table->timestamp('lot_updated_at')->nullable();

            $table->foreign('lot_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('lot_mrc_id')->references('mrc_id')->on('ali_marca');

            $table->index(['lot_itm_id', 'lot_dt_validade'], 'ali_lote_item_validade_idx');
        });

        DB::statement('ALTER TABLE ali_lote ADD CONSTRAINT ali_lote_validade_apos_fabricacao CHECK (lot_dt_validade IS NULL OR lot_dt_fabricacao IS NULL OR lot_dt_validade >= lot_dt_fabricacao)');

        /*
         * Razão do estoque (ledger). Append-only: sem update, sem soft delete.
         * Erro se corrige com ESTORNO apontando para o movimento original —
         * apagar linha faria o saldo divergir do histórico sem deixar rastro.
         */
        Schema::create('ali_movimento', function (Blueprint $table) {
            $table->bigIncrements('mov_id');
            $table->unsignedBigInteger('mov_esc_id')->nullable(); // nulo = estoque central
            $table->unsignedInteger('mov_itm_id');
            $table->unsignedInteger('mov_lot_id');
            $table->unsignedInteger('mov_umd_id');   // unidade digitada pelo usuário
            $table->unsignedInteger('mov_frn_id')->nullable();
            $table->unsignedInteger('mov_ofn_id')->nullable();
            $table->unsignedInteger('mov_tpr_id')->nullable(); // tipo de retirada
            $table->unsignedBigInteger('mov_fun_id')->nullable();
            $table->unsignedBigInteger('mov_usr_id')->nullable();

            $table->string('mov_tipo', 12); // ENTRADA|SAIDA|RETIRADA|CONSUMO|AJUSTE|ESTORNO
            $table->decimal('mov_qtd', 16, 4);
            // Sempre na unidade base do item: sem isso, entrar em CX e sair em KG
            // não fecha saldo.
            $table->decimal('mov_qtd_base', 16, 4);
            $table->decimal('mov_vl_unitario', 16, 4)->nullable(); // custo na unidade base
            $table->decimal('mov_vl_total', 16, 2)->nullable();
            $table->date('mov_dt');

            // Transferência = 2 movimentos (SAIDA + ENTRADA) ligados entre si.
            $table->unsignedBigInteger('mov_trf_par_id')->nullable();
            // Estorno aponta para o movimento que anula.
            $table->unsignedBigInteger('mov_estorno_de_id')->nullable();

            $table->string('mov_origem', 60)->nullable(); // tabela+id que originou
            $table->text('mov_obs')->nullable();
            $table->timestamp('mov_created_at')->nullable();
            $table->timestamp('mov_updated_at')->nullable();

            $table->foreign('mov_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('mov_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('mov_lot_id')->references('lot_id')->on('ali_lote');
            $table->foreign('mov_umd_id')->references('umd_id')->on('ali_unidade_medida');
            $table->foreign('mov_frn_id')->references('frn_id')->on('ali_fornecedor');
            $table->foreign('mov_ofn_id')->references('ofn_id')->on('ali_ordem_fornecimento');
            $table->foreign('mov_tpr_id')->references('tpr_id')->on('ali_tipo_retirada');
            $table->foreign('mov_fun_id')->references('fun_id')->on('edu_funcionario');
            $table->foreign('mov_usr_id')->references('id')->on('users');
            $table->foreign('mov_trf_par_id')->references('mov_id')->on('ali_movimento');
            $table->foreign('mov_estorno_de_id')->references('mov_id')->on('ali_movimento');

            $table->index(['mov_esc_id', 'mov_itm_id', 'mov_dt'], 'ali_movimento_estoque_idx');
            $table->index('mov_dt', 'ali_movimento_dt_idx');
        });

        DB::statement("ALTER TABLE ali_movimento ADD CONSTRAINT ali_movimento_tipo_valido CHECK (mov_tipo IN ('ENTRADA', 'SAIDA', 'RETIRADA', 'CONSUMO', 'AJUSTE', 'ESTORNO'))");
        DB::statement('ALTER TABLE ali_movimento ADD CONSTRAINT ali_movimento_qtd_positiva CHECK (mov_qtd > 0 AND mov_qtd_base > 0)');
        // Um movimento não pode ser estorno e transferência ao mesmo tempo.
        DB::statement('ALTER TABLE ali_movimento ADD CONSTRAINT ali_movimento_par_exclusivo CHECK (mov_trf_par_id IS NULL OR mov_estorno_de_id IS NULL)');

        /*
         * Saldo materializado — o "saldo em tempo real" das telas. Sempre na
         * unidade base do item. Atualizado na mesma transação do movimento, com
         * lock na linha; a reconciliação contra o ledger é comando à parte.
         */
        Schema::create('ali_estoque_saldo', function (Blueprint $table) {
            $table->bigIncrements('sld_id');
            $table->unsignedBigInteger('sld_esc_id')->nullable(); // nulo = central
            $table->unsignedInteger('sld_itm_id');
            $table->unsignedInteger('sld_lot_id');
            $table->decimal('sld_qtd_base', 16, 4)->default(0);
            $table->decimal('sld_vl_custo_medio', 16, 4)->default(0);
            $table->timestamp('sld_created_at')->nullable();
            $table->timestamp('sld_updated_at')->nullable();

            $table->foreign('sld_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('sld_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('sld_lot_id')->references('lot_id')->on('ali_lote');

            $table->index(['sld_esc_id', 'sld_itm_id'], 'ali_estoque_saldo_estoque_idx');
        });

        DB::statement('ALTER TABLE ali_estoque_saldo ADD CONSTRAINT ali_estoque_saldo_nao_negativo CHECK (sld_qtd_base >= 0)');

        // Um saldo por (estoque, item, lote). Dois índices por causa do nulo:
        // no Postgres NULL != NULL, então um unique comum deixaria duplicar o central.
        DB::statement('CREATE UNIQUE INDEX ali_estoque_saldo_escola_unique ON ali_estoque_saldo (sld_esc_id, sld_itm_id, sld_lot_id) WHERE sld_esc_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ali_estoque_saldo_central_unique ON ali_estoque_saldo (sld_itm_id, sld_lot_id) WHERE sld_esc_id IS NULL');

        /*
         * Recebimento: conferência da entrega do fornecedor contra a ordem.
         * Aceitar o recebimento é o que dá entrada no estoque central.
         */
        Schema::create('ali_recebimento', function (Blueprint $table) {
            $table->increments('rcb_id');
            $table->unsignedInteger('rcb_ofn_id');
            $table->unsignedBigInteger('rcb_fun_id')->nullable();
            $table->unsignedBigInteger('rcb_usr_id')->nullable();
            $table->date('rcb_dt');
            $table->string('rcb_tipo', 24)->default('ORDINARIO'); // ORDINARIO|AGRICULTURA_FAMILIAR
            $table->string('rcb_situacao', 12)->default('PENDENTE'); // PENDENTE|ACEITO|PARCIAL|REJEITADO
            $table->string('rcb_nr_nota', 30)->nullable();
            $table->text('rcb_obs')->nullable();
            $table->timestamp('rcb_created_at')->nullable();
            $table->timestamp('rcb_updated_at')->nullable();
            $table->softDeletesTz('rcb_deleted_at');

            $table->foreign('rcb_ofn_id')->references('ofn_id')->on('ali_ordem_fornecimento');
            $table->foreign('rcb_fun_id')->references('fun_id')->on('edu_funcionario');
            $table->foreign('rcb_usr_id')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE ali_recebimento ADD CONSTRAINT ali_recebimento_tipo_valido CHECK (rcb_tipo IN ('ORDINARIO', 'AGRICULTURA_FAMILIAR'))");
        DB::statement("ALTER TABLE ali_recebimento ADD CONSTRAINT ali_recebimento_situacao_valida CHECK (rcb_situacao IN ('PENDENTE', 'ACEITO', 'PARCIAL', 'REJEITADO'))");

        Schema::create('ali_recebimento_item', function (Blueprint $table) {
            $table->increments('rci_id');
            $table->unsignedInteger('rci_rcb_id');
            $table->unsignedInteger('rci_ofi_id');   // item da ordem sendo conferido
            $table->unsignedInteger('rci_itm_id');
            $table->unsignedInteger('rci_lot_id')->nullable(); // lote criado na conferência
            $table->unsignedInteger('rci_umd_id');
            $table->decimal('rci_qtd_prevista', 16, 4);
            $table->decimal('rci_qtd_recebida', 16, 4)->default(0);
            $table->decimal('rci_qtd_rejeitada', 16, 4)->default(0);
            $table->string('rci_motivo_rejeicao', 120)->nullable();
            $table->timestamp('rci_created_at')->nullable();
            $table->timestamp('rci_updated_at')->nullable();

            $table->foreign('rci_rcb_id')->references('rcb_id')->on('ali_recebimento')->cascadeOnDelete();
            $table->foreign('rci_ofi_id')->references('ofi_id')->on('ali_ordem_fornecimento_item');
            $table->foreign('rci_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('rci_lot_id')->references('lot_id')->on('ali_lote');
            $table->foreign('rci_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['rci_rcb_id', 'rci_ofi_id'], 'ali_recebimento_item_unique');
        });

        DB::statement('ALTER TABLE ali_recebimento_item ADD CONSTRAINT ali_rci_qtds_nao_negativas CHECK (rci_qtd_recebida >= 0 AND rci_qtd_rejeitada >= 0)');
        // Rejeitar sem dizer o motivo é o que a conferência existe para evitar.
        DB::statement('ALTER TABLE ali_recebimento_item ADD CONSTRAINT ali_rci_rejeicao_justificada CHECK (rci_qtd_rejeitada = 0 OR rci_motivo_rejeicao IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_recebimento_item');
        Schema::dropIfExists('ali_recebimento');
        Schema::dropIfExists('ali_estoque_saldo');
        Schema::dropIfExists('ali_movimento');
        Schema::dropIfExists('ali_lote');
    }
};
