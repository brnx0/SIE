<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos simples da alimentação escolar: categoria de alimento, tipo de item,
 * marca e tipo de retirada. Sem soft delete — inativação por `*_fl_ativo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_categoria_alimento', function (Blueprint $table) {
            $table->increments('cat_id');
            $table->string('cat_nome', 80);
            $table->boolean('cat_fl_ativo')->default(true);
            $table->timestamp('cat_created_at')->nullable();
            $table->timestamp('cat_updated_at')->nullable();
        });

        Schema::create('ali_tipo_item', function (Blueprint $table) {
            $table->increments('tpi_id');
            $table->string('tpi_nome', 80);
            $table->boolean('tpi_fl_ativo')->default(true);
            $table->timestamp('tpi_created_at')->nullable();
            $table->timestamp('tpi_updated_at')->nullable();
        });

        Schema::create('ali_marca', function (Blueprint $table) {
            $table->increments('mrc_id');
            $table->string('mrc_nome', 80);
            $table->boolean('mrc_fl_ativo')->default(true);
            $table->timestamp('mrc_created_at')->nullable();
            $table->timestamp('mrc_updated_at')->nullable();
        });

        Schema::create('ali_tipo_retirada', function (Blueprint $table) {
            $table->increments('tpr_id');
            $table->string('tpr_nome', 80);
            $table->boolean('tpr_fl_ativo')->default(true);
            $table->timestamp('tpr_created_at')->nullable();
            $table->timestamp('tpr_updated_at')->nullable();
        });

        foreach ([
            'ali_categoria_alimento' => 'cat_nome',
            'ali_tipo_item'          => 'tpi_nome',
            'ali_marca'              => 'mrc_nome',
            'ali_tipo_retirada'      => 'tpr_nome',
        ] as $tabela => $coluna) {
            DB::statement("CREATE UNIQUE INDEX {$tabela}_{$coluna}_unique ON {$tabela} (UPPER({$coluna}))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_tipo_retirada');
        Schema::dropIfExists('ali_marca');
        Schema::dropIfExists('ali_tipo_item');
        Schema::dropIfExists('ali_categoria_alimento');
    }
};
