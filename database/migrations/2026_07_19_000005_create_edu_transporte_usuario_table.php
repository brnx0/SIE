<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_usuario', function (Blueprint $table) {
            $table->bigIncrements('tue_id');
            $table->string('tue_nome', 150);
            $table->string('tue_cpf', 11)->nullable();
            $table->date('tue_dt_nascimento')->nullable();
            $table->string('tue_telefone', 20)->nullable();
            $table->unsignedBigInteger('tue_esc_id')->nullable(); // escola/instituição de ensino que frequenta
            $table->text('tue_justificativa'); // justificativa de utilização (obrigatória)
            $table->boolean('tue_fl_ativo')->default(true);
            $table->timestamp('tue_created_at')->nullable();
            $table->timestamp('tue_updated_at')->nullable();
            $table->softDeletesTz('tue_deleted_at');

            $table->foreign('tue_esc_id')->references('esc_id')->on('edu_escola');
            $table->index('tue_esc_id');
        });

        DB::statement('CREATE UNIQUE INDEX edu_transporte_usuario_cpf_unique ON edu_transporte_usuario (tue_cpf) WHERE tue_cpf IS NOT NULL AND tue_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_usuario');
    }
};
