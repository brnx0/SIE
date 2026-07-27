<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsável pela retirada informado como texto livre.
 *
 * A retirada já registra o responsável cadastrado em `mov_fun_id`, mas quem retira
 * na cozinha nem sempre é funcionário lançado no sistema. `mov_responsavel` guarda
 * o nome digitado nesses casos; os dois nunca vêm preenchidos ao mesmo tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_movimento', function (Blueprint $table) {
            $table->string('mov_responsavel', 120)->nullable()->after('mov_fun_id');
        });
    }

    public function down(): void
    {
        Schema::table('ali_movimento', function (Blueprint $table) {
            $table->dropColumn('mov_responsavel');
        });
    }
};
