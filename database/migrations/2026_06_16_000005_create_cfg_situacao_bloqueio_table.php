<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_situacao_bloqueio', function (Blueprint $table) {
            $table->bigIncrements('sba_id');
            $table->smallInteger('sba_tas_cod');
            $table->unsignedBigInteger('sba_created_by_id')->nullable();
            $table->timestamp('sba_created_at')->nullable();
            $table->timestamp('sba_updated_at')->nullable();

            // Cada situação aparece no máximo uma vez na lista de bloqueio
            $table->unique('sba_tas_cod');

            $table->foreign('sba_tas_cod')->references('tas_cod')->on('edu_turma_aluno_situacao')->cascadeOnDelete();
            $table->foreign('sba_created_by_id')->references('id')->on('users')->nullOnDelete();
        });

        // Situações que bloqueiam registros do aluno por padrão:
        // 2 Desistente, 3 Transferido, 4 Evadido, 5 Óbito, 15 Remanejado,
        // 19 Transferido da Rede, 20 Matrícula Indevida, 30 Matrícula Indeferida, 34 Cancelamento de Matrícula
        $codigos = [2, 3, 4, 5, 15, 19, 20, 30, 34];
        $now = now();
        DB::table('cfg_situacao_bloqueio')->insert(
            array_map(fn ($c) => [
                'sba_tas_cod'    => $c,
                'sba_created_at' => $now,
                'sba_updated_at' => $now,
            ], $codigos)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_situacao_bloqueio');
    }
};
