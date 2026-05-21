<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_ano_letivo', function (Blueprint $t) {
            $t->bigIncrements('anl_id');
            $t->smallInteger('anl_ano');
            $t->date('anl_dt_inicio_ano');
            $t->date('anl_dt_inicio_1sem');
            $t->date('anl_dt_inicio_2sem');
            $t->date('anl_dt_fim');
            $t->date('anl_dt_censo')->nullable();
            $t->boolean('anl_fl_em_exercicio')->default(false);
            $t->boolean('anl_fl_progressao_parcial')->default(false);
            $t->boolean('anl_fl_aprovacao_conselho_freq')->default(false);

            $t->unsignedBigInteger('anl_created_by_id')->nullable();
            $t->unsignedBigInteger('anl_updated_by_id')->nullable();

            $t->timestamp('anl_created_at')->nullable();
            $t->timestamp('anl_updated_at')->nullable();
            $t->softDeletes('anl_deleted_at');

            $t->foreign('anl_created_by_id')->references('id')->on('users')->nullOnDelete();
            $t->foreign('anl_updated_by_id')->references('id')->on('users')->nullOnDelete();
        });

        // Ano único entre registros não excluídos (partial index Postgres).
        DB::statement('CREATE UNIQUE INDEX cfg_ano_letivo_anl_ano_unique ON cfg_ano_letivo (anl_ano) WHERE anl_deleted_at IS NULL');

        // Apenas um registro pode estar "em exercício".
        DB::statement('CREATE UNIQUE INDEX cfg_ano_letivo_em_exercicio_unique ON cfg_ano_letivo ((1)) WHERE anl_fl_em_exercicio = true AND anl_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_ano_letivo');
    }
};
