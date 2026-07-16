<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Contrato guarda sempre os valores VIGENTES (valor, vigência, objeto) —
         * é o que a tela lê, sem cálculo. Cada alteração é lançada como aditivo,
         * que atualiza o contrato e grava o de/para na mesma transação.
         */
        Schema::create('ali_contrato', function (Blueprint $table) {
            $table->increments('ctr_id');
            $table->unsignedInteger('ctr_frn_id');
            $table->string('ctr_nr_contrato', 30);
            $table->integer('ctr_ano');
            $table->text('ctr_objeto');
            $table->string('ctr_modalidade', 40)->nullable();
            $table->date('ctr_dt_assinatura')->nullable();
            $table->date('ctr_dt_inicio');
            $table->date('ctr_dt_fim');
            $table->decimal('ctr_vl_total', 16, 2);
            $table->string('ctr_situacao', 20)->default('VIGENTE'); // VIGENTE|SUSPENSO|ENCERRADO|RESCINDIDO
            $table->timestamp('ctr_created_at')->nullable();
            $table->timestamp('ctr_updated_at')->nullable();
            $table->softDeletesTz('ctr_deleted_at');

            $table->foreign('ctr_frn_id')->references('frn_id')->on('ali_fornecedor');
        });

        DB::statement('CREATE UNIQUE INDEX ali_contrato_numero_ano_unique ON ali_contrato (UPPER(ctr_nr_contrato), ctr_ano) WHERE ctr_deleted_at IS NULL');
        DB::statement('ALTER TABLE ali_contrato ADD CONSTRAINT ali_contrato_vigencia_valida CHECK (ctr_dt_fim >= ctr_dt_inicio)');
        DB::statement('ALTER TABLE ali_contrato ADD CONSTRAINT ali_contrato_valor_positivo CHECK (ctr_vl_total > 0)');
        DB::statement("ALTER TABLE ali_contrato ADD CONSTRAINT ali_contrato_situacao_valida CHECK (ctr_situacao IN ('VIGENTE', 'SUSPENSO', 'ENCERRADO', 'RESCINDIDO'))");

        /* Itens contratados: quantidade e preço unitário por item. */
        Schema::create('ali_contrato_item', function (Blueprint $table) {
            $table->increments('cti_id');
            $table->unsignedInteger('cti_ctr_id');
            $table->unsignedInteger('cti_itm_id');
            $table->unsignedInteger('cti_umd_id');
            $table->decimal('cti_qtd', 16, 4);
            $table->decimal('cti_vl_unitario', 16, 4);
            $table->timestamp('cti_created_at')->nullable();
            $table->timestamp('cti_updated_at')->nullable();

            $table->foreign('cti_ctr_id')->references('ctr_id')->on('ali_contrato')->cascadeOnDelete();
            $table->foreign('cti_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('cti_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['cti_ctr_id', 'cti_itm_id'], 'ali_contrato_item_contrato_item_unique');
        });

        DB::statement('ALTER TABLE ali_contrato_item ADD CONSTRAINT ali_contrato_item_qtd_positiva CHECK (cti_qtd > 0)');
        DB::statement('ALTER TABLE ali_contrato_item ADD CONSTRAINT ali_contrato_item_valor_positivo CHECK (cti_vl_unitario > 0)');

        /*
         * Aditivo = log de alteração do contrato. Guarda o de/para do que mudou;
         * o valor vigente fica no próprio contrato.
         */
        Schema::create('ali_contrato_aditivo', function (Blueprint $table) {
            $table->increments('adt_id');
            $table->unsignedInteger('adt_ctr_id');
            // Preenchido só em aditivo de QTD (a alteração é de um item específico).
            $table->unsignedInteger('adt_cti_id')->nullable();
            $table->string('adt_nr_aditivo', 30);
            $table->string('adt_tipo', 10); // VALOR|PRAZO|OBJETO|QTD
            $table->date('adt_dt_assinatura');

            $table->decimal('adt_vl_anterior', 16, 2)->nullable();
            $table->decimal('adt_vl_novo', 16, 2)->nullable();
            $table->date('adt_dt_fim_anterior')->nullable();
            $table->date('adt_dt_fim_nova')->nullable();
            $table->text('adt_objeto_anterior')->nullable();
            $table->text('adt_objeto_novo')->nullable();
            $table->decimal('adt_qtd_anterior', 16, 4)->nullable();
            $table->decimal('adt_qtd_nova', 16, 4)->nullable();

            $table->text('adt_justificativa')->nullable();
            $table->unsignedBigInteger('adt_usr_id')->nullable();
            $table->timestamp('adt_created_at')->nullable();
            $table->timestamp('adt_updated_at')->nullable();

            $table->foreign('adt_ctr_id')->references('ctr_id')->on('ali_contrato')->cascadeOnDelete();
            $table->foreign('adt_cti_id')->references('cti_id')->on('ali_contrato_item')->nullOnDelete();
            $table->foreign('adt_usr_id')->references('id')->on('users');

            $table->unique(['adt_ctr_id', 'adt_nr_aditivo'], 'ali_contrato_aditivo_numero_unique');
        });

        DB::statement("ALTER TABLE ali_contrato_aditivo ADD CONSTRAINT ali_contrato_aditivo_tipo_valido CHECK (adt_tipo IN ('VALOR', 'PRAZO', 'OBJETO', 'QTD'))");

        /* Empenho: reserva orçamentária que custeia as ordens de fornecimento. */
        Schema::create('ali_empenho', function (Blueprint $table) {
            $table->increments('emp_id');
            $table->unsignedInteger('emp_ctr_id');
            $table->string('emp_nr_empenho', 30);
            $table->date('emp_dt');
            $table->decimal('emp_vl_empenhado', 16, 2);
            $table->string('emp_situacao', 20)->default('ATIVO'); // ATIVO|CANCELADO
            $table->text('emp_obs')->nullable();
            $table->timestamp('emp_created_at')->nullable();
            $table->timestamp('emp_updated_at')->nullable();
            $table->softDeletesTz('emp_deleted_at');

            $table->foreign('emp_ctr_id')->references('ctr_id')->on('ali_contrato');
        });

        DB::statement('CREATE UNIQUE INDEX ali_empenho_numero_unique ON ali_empenho (UPPER(emp_nr_empenho)) WHERE emp_deleted_at IS NULL');
        DB::statement('ALTER TABLE ali_empenho ADD CONSTRAINT ali_empenho_valor_positivo CHECK (emp_vl_empenhado > 0)');
        DB::statement("ALTER TABLE ali_empenho ADD CONSTRAINT ali_empenho_situacao_valida CHECK (emp_situacao IN ('ATIVO', 'CANCELADO'))");

        /* Ordem de fornecimento: o pedido efetivo ao fornecedor. */
        Schema::create('ali_ordem_fornecimento', function (Blueprint $table) {
            $table->increments('ofn_id');
            $table->unsignedInteger('ofn_ctr_id');
            $table->unsignedInteger('ofn_emp_id')->nullable();
            $table->unsignedInteger('ofn_loc_id'); // onde o fornecedor entrega
            $table->string('ofn_nr_ordem', 30);
            $table->date('ofn_dt_emissao');
            $table->date('ofn_dt_entrega_prevista')->nullable();
            $table->string('ofn_situacao', 20)->default('EMITIDA'); // EMITIDA|ENTREGUE|PARCIAL|CANCELADA
            $table->text('ofn_obs')->nullable();
            $table->timestamp('ofn_created_at')->nullable();
            $table->timestamp('ofn_updated_at')->nullable();
            $table->softDeletesTz('ofn_deleted_at');

            $table->foreign('ofn_ctr_id')->references('ctr_id')->on('ali_contrato');
            $table->foreign('ofn_emp_id')->references('emp_id')->on('ali_empenho');
            $table->foreign('ofn_loc_id')->references('loc_id')->on('ali_local_estoque');
        });

        DB::statement('CREATE UNIQUE INDEX ali_ordem_fornecimento_numero_unique ON ali_ordem_fornecimento (UPPER(ofn_nr_ordem)) WHERE ofn_deleted_at IS NULL');
        DB::statement("ALTER TABLE ali_ordem_fornecimento ADD CONSTRAINT ali_ordem_fornecimento_situacao_valida CHECK (ofn_situacao IN ('EMITIDA', 'ENTREGUE', 'PARCIAL', 'CANCELADA'))");

        Schema::create('ali_ordem_fornecimento_item', function (Blueprint $table) {
            $table->increments('ofi_id');
            $table->unsignedInteger('ofi_ofn_id');
            $table->unsignedInteger('ofi_itm_id');
            $table->unsignedInteger('ofi_umd_id');
            $table->decimal('ofi_qtd', 16, 4);
            $table->decimal('ofi_vl_unitario', 16, 4);
            // Preenchida pelo recebimento (fase de estoque).
            $table->decimal('ofi_qtd_entregue', 16, 4)->default(0);
            $table->timestamp('ofi_created_at')->nullable();
            $table->timestamp('ofi_updated_at')->nullable();

            $table->foreign('ofi_ofn_id')->references('ofn_id')->on('ali_ordem_fornecimento')->cascadeOnDelete();
            $table->foreign('ofi_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('ofi_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['ofi_ofn_id', 'ofi_itm_id'], 'ali_ordem_fornecimento_item_ordem_item_unique');
        });

        DB::statement('ALTER TABLE ali_ordem_fornecimento_item ADD CONSTRAINT ali_ofi_qtd_positiva CHECK (ofi_qtd > 0)');
        DB::statement('ALTER TABLE ali_ordem_fornecimento_item ADD CONSTRAINT ali_ofi_entregue_nao_negativa CHECK (ofi_qtd_entregue >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_ordem_fornecimento_item');
        Schema::dropIfExists('ali_ordem_fornecimento');
        Schema::dropIfExists('ali_empenho');
        Schema::dropIfExists('ali_contrato_aditivo');
        Schema::dropIfExists('ali_contrato_item');
        Schema::dropIfExists('ali_contrato');
    }
};
