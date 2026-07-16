<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_prato', function (Blueprint $table) {
            $table->increments('prt_id');
            $table->string('prt_nome', 120);
            $table->text('prt_descricao')->nullable();
            $table->text('prt_modo_preparo')->nullable();
            $table->boolean('prt_fl_ativo')->default(true);
            $table->timestamp('prt_created_at')->nullable();
            $table->timestamp('prt_updated_at')->nullable();
            $table->softDeletesTz('prt_deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX ali_prato_prt_nome_unique ON ali_prato (UPPER(prt_nome)) WHERE prt_deleted_at IS NULL');

        // Ficha técnica do prato: quantidade por aluno de cada item.
        Schema::create('ali_prato_item', function (Blueprint $table) {
            $table->increments('pri_id');
            $table->unsignedInteger('pri_prt_id');
            $table->unsignedInteger('pri_itm_id');
            $table->unsignedInteger('pri_umd_id');
            $table->decimal('pri_qtd_per_capita', 14, 4);
            $table->timestamp('pri_created_at')->nullable();
            $table->timestamp('pri_updated_at')->nullable();

            $table->foreign('pri_prt_id')->references('prt_id')->on('ali_prato')->cascadeOnDelete();
            $table->foreign('pri_itm_id')->references('itm_id')->on('ali_item');
            $table->foreign('pri_umd_id')->references('umd_id')->on('ali_unidade_medida');

            $table->unique(['pri_prt_id', 'pri_itm_id'], 'ali_prato_item_prato_item_unique');
        });

        DB::statement('ALTER TABLE ali_prato_item ADD CONSTRAINT ali_prato_item_qtd_positiva CHECK (pri_qtd_per_capita > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_prato_item');
        Schema::dropIfExists('ali_prato');
    }
};
