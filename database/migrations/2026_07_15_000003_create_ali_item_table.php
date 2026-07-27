<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_item', function (Blueprint $table) {
            $table->increments('itm_id');
            $table->unsignedInteger('itm_tpi_id');
            $table->unsignedInteger('itm_cat_id');
            // Unidade base de controle: todo saldo e movimento são convertidos para ela.
            $table->unsignedInteger('itm_umd_id');
            $table->string('itm_cd_item', 20)->nullable();
            $table->string('itm_nome', 120);
            $table->text('itm_especificacao')->nullable();
            $table->boolean('itm_fl_perecivel')->default(false);
            $table->boolean('itm_fl_agricultura_familiar')->default(false);
            $table->integer('itm_validade_dias')->nullable();
            $table->decimal('itm_estoque_minimo', 14, 4)->nullable();
            $table->boolean('itm_fl_ativo')->default(true);
            $table->timestamp('itm_created_at')->nullable();
            $table->timestamp('itm_updated_at')->nullable();
            $table->softDeletesTz('itm_deleted_at');

            $table->foreign('itm_tpi_id')->references('tpi_id')->on('ali_tipo_item');
            $table->foreign('itm_cat_id')->references('cat_id')->on('ali_categoria_alimento');
            $table->foreign('itm_umd_id')->references('umd_id')->on('ali_unidade_medida');
        });

        DB::statement('CREATE UNIQUE INDEX ali_item_itm_cd_item_unique ON ali_item (itm_cd_item) WHERE itm_cd_item IS NOT NULL AND itm_deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ali_item_itm_nome_unique ON ali_item (UPPER(itm_nome)) WHERE itm_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_item');
    }
};
