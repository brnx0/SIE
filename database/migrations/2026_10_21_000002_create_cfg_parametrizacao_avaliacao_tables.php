<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametrização das avaliações por UNIDADE letiva (rede inteira): quais
 * instrumentos avaliativos a unidade terá e o valor máximo de cada um. Com
 * pav_fl_bloqueado, o professor não altera o valor ao criar a avaliação.
 * Unidade sem parametrização = comportamento livre (como antes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_parametrizacao_avaliacao', function (Blueprint $table) {
            $table->id('pav_id');
            $table->unsignedBigInteger('pav_uni_id')->unique();
            $table->boolean('pav_fl_bloqueado')->default(false);
            $table->foreignId('pav_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pav_created_at')->nullable();
            $table->timestamp('pav_updated_at')->nullable();

            $table->foreign('pav_uni_id')->references('uni_id')->on('cfg_unidade')->cascadeOnDelete();
        });

        Schema::create('cfg_parametrizacao_avaliacao_item', function (Blueprint $table) {
            $table->id('pai_id');
            $table->unsignedBigInteger('pai_pav_id');
            $table->unsignedBigInteger('pai_iav_id');
            $table->decimal('pai_valor', 4, 2); // valor máximo que o aluno pode obter no instrumento
            $table->timestamp('pai_created_at')->nullable();
            $table->timestamp('pai_updated_at')->nullable();

            $table->foreign('pai_pav_id')->references('pav_id')->on('cfg_parametrizacao_avaliacao')->cascadeOnDelete();
            $table->foreign('pai_iav_id')->references('iav_id')->on('edu_instrumento_avaliativo')->restrictOnDelete();
            $table->unique(['pai_pav_id', 'pai_iav_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_parametrizacao_avaliacao_item');
        Schema::dropIfExists('cfg_parametrizacao_avaliacao');
    }
};
