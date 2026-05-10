<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_gerencia_regional', function (Blueprint $t) {
            $t->bigIncrements('ger_id');
            $t->string('ger_nome', 100);
            $t->char('ger_uf', 2)->nullable();
            $t->string('ger_sigla', 20)->nullable();
            $t->boolean('ger_fl_ativo')->default(true);
            $t->timestamp('ger_created_at')->nullable();
            $t->timestamp('ger_updated_at')->nullable();
            $t->softDeletes('ger_deleted_at');

            $t->unique('ger_nome');
            $t->index('ger_uf');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_gerencia_regional');
    }
};
