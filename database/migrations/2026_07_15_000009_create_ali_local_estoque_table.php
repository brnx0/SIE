<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local de estoque unificado: o estoque central e o de cada escola vivem na
 * mesma tabela, distinguidos por `loc_tipo`.
 *
 * Saldo e movimento apontam para o local, então uma transferência central→escola
 * é só uma saída num local + uma entrada em outro — sem duplicar a lógica de saldo.
 *
 * Criada aqui (e não na fase de estoque) porque a ordem de fornecimento precisa
 * dizer onde o fornecedor entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_local_estoque', function (Blueprint $table) {
            $table->increments('loc_id');
            // Nulo quando CENTRAL — o estoque central não pertence a uma escola.
            $table->unsignedBigInteger('loc_esc_id')->nullable();
            $table->string('loc_tipo', 20); // CENTRAL | ESCOLAR
            $table->string('loc_nome', 120);
            $table->boolean('loc_fl_ativo')->default(true);
            $table->timestamp('loc_created_at')->nullable();
            $table->timestamp('loc_updated_at')->nullable();

            $table->foreign('loc_esc_id')->references('esc_id')->on('edu_escola');
        });

        DB::statement("ALTER TABLE ali_local_estoque ADD CONSTRAINT ali_local_estoque_tipo_valido CHECK (loc_tipo IN ('CENTRAL', 'ESCOLAR'))");

        // Coerência tipo × escola: ESCOLAR exige escola, CENTRAL não pode ter.
        DB::statement("
            ALTER TABLE ali_local_estoque ADD CONSTRAINT ali_local_estoque_escola_coerente CHECK (
                (loc_tipo = 'ESCOLAR' AND loc_esc_id IS NOT NULL)
                OR (loc_tipo = 'CENTRAL' AND loc_esc_id IS NULL)
            )
        ");

        // Uma escola tem um estoque só; e o central é único na rede.
        DB::statement("CREATE UNIQUE INDEX ali_local_estoque_escola_unique ON ali_local_estoque (loc_esc_id) WHERE loc_esc_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX ali_local_estoque_central_unique ON ali_local_estoque ((1)) WHERE loc_tipo = 'CENTRAL'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_local_estoque');
    }
};
