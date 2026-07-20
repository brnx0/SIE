<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_passageiro', function (Blueprint $table) {
            $table->bigIncrements('pas_id');
            $table->unsignedBigInteger('pas_rot_id');
            $table->unsignedBigInteger('pas_pto_id'); // ponto de embarque
            $table->unsignedBigInteger('pas_aln_id')->nullable(); // aluno da rede
            $table->unsignedBigInteger('pas_tue_id')->nullable(); // usuário externo
            $table->date('pas_dt_inicio')->nullable();
            $table->date('pas_dt_fim')->nullable();
            $table->string('pas_obs', 200)->nullable();
            $table->timestamp('pas_created_at')->nullable();
            $table->timestamp('pas_updated_at')->nullable();
            $table->softDeletesTz('pas_deleted_at');

            $table->foreign('pas_rot_id')->references('rot_id')->on('edu_transporte_rota');
            $table->foreign('pas_pto_id')->references('pto_id')->on('edu_transporte_ponto');
            $table->foreign('pas_aln_id')->references('aln_id')->on('edu_aluno');
            $table->foreign('pas_tue_id')->references('tue_id')->on('edu_transporte_usuario');
            $table->index('pas_rot_id');
            $table->index('pas_pto_id');
            $table->index('pas_aln_id');
        });

        // Passageiro é exatamente UM de: aluno da rede OU usuário externo.
        DB::statement('ALTER TABLE edu_transporte_passageiro ADD CONSTRAINT edu_transporte_passageiro_origem_check CHECK (((pas_aln_id IS NOT NULL)::int + (pas_tue_id IS NOT NULL)::int) = 1)');
        DB::statement('CREATE UNIQUE INDEX edu_transporte_passageiro_aln_unique ON edu_transporte_passageiro (pas_rot_id, pas_aln_id) WHERE pas_aln_id IS NOT NULL AND pas_deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX edu_transporte_passageiro_tue_unique ON edu_transporte_passageiro (pas_rot_id, pas_tue_id) WHERE pas_tue_id IS NOT NULL AND pas_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_passageiro');
    }
};
