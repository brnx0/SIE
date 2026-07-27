<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversão entre unidades de medida.
 *
 * `cvu_itm_id` nulo   = regra global (ex.: KG → G, fator 1000).
 * `cvu_itm_id` preenchido = regra específica do item (ex.: CX de arroz → KG, fator 30).
 * A resolução procura a regra do item primeiro e só depois cai na global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_conversao_unidade', function (Blueprint $table) {
            $table->increments('cvu_id');
            $table->unsignedInteger('cvu_itm_id')->nullable();
            $table->unsignedInteger('cvu_umd_origem_id');
            $table->unsignedInteger('cvu_umd_destino_id');
            $table->decimal('cvu_fator', 18, 6);
            $table->boolean('cvu_fl_ativo')->default(true);
            $table->timestamp('cvu_created_at')->nullable();
            $table->timestamp('cvu_updated_at')->nullable();

            $table->foreign('cvu_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('cvu_umd_origem_id')->references('umd_id')->on('ali_unidade_medida');
            $table->foreign('cvu_umd_destino_id')->references('umd_id')->on('ali_unidade_medida');
        });

        // Unique separado por causa do nulo: no Postgres NULL != NULL, então um unique
        // comum deixaria cadastrar a mesma regra global várias vezes.
        DB::statement('CREATE UNIQUE INDEX ali_conversao_unidade_item_unique ON ali_conversao_unidade (cvu_itm_id, cvu_umd_origem_id, cvu_umd_destino_id) WHERE cvu_itm_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ali_conversao_unidade_global_unique ON ali_conversao_unidade (cvu_umd_origem_id, cvu_umd_destino_id) WHERE cvu_itm_id IS NULL');

        DB::statement('ALTER TABLE ali_conversao_unidade ADD CONSTRAINT ali_conversao_unidade_fator_positivo CHECK (cvu_fator > 0)');
        DB::statement('ALTER TABLE ali_conversao_unidade ADD CONSTRAINT ali_conversao_unidade_unidades_distintas CHECK (cvu_umd_origem_id <> cvu_umd_destino_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_conversao_unidade');
    }
};
