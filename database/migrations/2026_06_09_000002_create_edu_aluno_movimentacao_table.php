<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_aluno_movimentacao', function (Blueprint $table) {
            $table->bigIncrements('mva_id');
            $table->unsignedBigInteger('mva_aln_id');
            $table->smallInteger('mva_tmv_cod');
            $table->unsignedBigInteger('mva_tma_id_origem')->nullable();
            $table->unsignedBigInteger('mva_tma_id_destino')->nullable();
            $table->date('mva_dt_movimentacao');
            $table->string('mva_protocolo', 50)->nullable();
            $table->text('mva_observacao')->nullable();
            $table->json('mva_tmas_extras')->nullable();
            $table->unsignedBigInteger('mva_user_id')->nullable();
            $table->boolean('mva_fl_cancelada')->default(false);
            $table->text('mva_cancelada_motivo')->nullable();
            $table->timestamp('mva_created_at')->nullable();
            $table->timestamp('mva_updated_at')->nullable();
            $table->softDeletes('mva_deleted_at');

            $table->foreign('mva_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();
            $table->foreign('mva_tmv_cod')->references('tmv_cod')->on('edu_tipo_movimentacao')->restrictOnDelete();
            $table->foreign('mva_tma_id_origem')->references('tma_id')->on('edu_turma_aluno')->nullOnDelete();
            $table->foreign('mva_tma_id_destino')->references('tma_id')->on('edu_turma_aluno')->nullOnDelete();

            $table->index(['mva_aln_id', 'mva_dt_movimentacao']);
            $table->index('mva_tmv_cod');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_aluno_movimentacao');
    }
};
