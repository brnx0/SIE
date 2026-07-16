<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_cardapio', function (Blueprint $table) {
            $table->increments('crd_id');
            // Nulo = cardápio padrão da rede; preenchido = cardápio próprio da escola,
            // que tem precedência sobre o padrão.
            $table->unsignedBigInteger('crd_esc_id')->nullable();
            $table->unsignedBigInteger('crd_anl_id');
            $table->string('crd_nome', 120);
            $table->date('crd_dt_inicio');
            $table->date('crd_dt_fim');
            $table->boolean('crd_fl_ativo')->default(true);
            $table->timestamp('crd_created_at')->nullable();
            $table->timestamp('crd_updated_at')->nullable();
            $table->softDeletesTz('crd_deleted_at');

            $table->foreign('crd_esc_id')->references('esc_id')->on('edu_escola');
            $table->foreign('crd_anl_id')->references('anl_id')->on('cfg_ano_letivo');
        });

        DB::statement('ALTER TABLE ali_cardapio ADD CONSTRAINT ali_cardapio_vigencia_valida CHECK (crd_dt_fim >= crd_dt_inicio)');

        Schema::create('ali_cardapio_dia', function (Blueprint $table) {
            $table->increments('cdi_id');
            $table->unsignedInteger('cdi_crd_id');
            $table->unsignedInteger('cdi_prt_id');
            // Rodízio de semanas. Cardápio de semana fixa usa sempre 1 e a tela
            // não expõe o campo; se o cliente adotar rodízio, basta liberar na tela.
            $table->smallInteger('cdi_semana')->default(1);
            $table->smallInteger('cdi_dia_semana'); // 1 = segunda ... 7 = domingo
            $table->string('cdi_turno', 20);
            $table->string('cdi_refeicao', 40);
            $table->timestamp('cdi_created_at')->nullable();
            $table->timestamp('cdi_updated_at')->nullable();

            $table->foreign('cdi_crd_id')->references('crd_id')->on('ali_cardapio')->cascadeOnDelete();
            $table->foreign('cdi_prt_id')->references('prt_id')->on('ali_prato');

            $table->unique(
                ['cdi_crd_id', 'cdi_semana', 'cdi_dia_semana', 'cdi_turno', 'cdi_refeicao'],
                'ali_cardapio_dia_slot_unique'
            );
        });

        DB::statement('ALTER TABLE ali_cardapio_dia ADD CONSTRAINT ali_cardapio_dia_dia_semana_valido CHECK (cdi_dia_semana BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE ali_cardapio_dia ADD CONSTRAINT ali_cardapio_dia_semana_valida CHECK (cdi_semana BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_cardapio_dia');
        Schema::dropIfExists('ali_cardapio');
    }
};
