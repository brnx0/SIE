<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ali_unidade_medida', function (Blueprint $table) {
            $table->increments('umd_id');
            $table->string('umd_sigla', 10);
            $table->string('umd_nome', 60);
            $table->boolean('umd_fl_fracionavel')->default(true);
            $table->boolean('umd_fl_ativo')->default(true);
            $table->timestamp('umd_created_at')->nullable();
            $table->timestamp('umd_updated_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX ali_unidade_medida_umd_sigla_unique ON ali_unidade_medida (UPPER(umd_sigla))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ali_unidade_medida');
    }
};
