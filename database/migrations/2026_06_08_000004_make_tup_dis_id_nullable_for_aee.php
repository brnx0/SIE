<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('edu_turma_professor')) {
            return;
        }

        // AEE aloca só o professor (sem disciplina) → tup_dis_id nullable.
        Schema::table('edu_turma_professor', function (Blueprint $table) {
            $table->unsignedBigInteger('tup_dis_id')->nullable()->change();
        });

        // Unicidade p/ alocação AEE (sem disciplina): 1 professor por turma.
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS turma_professor_aee_unica ON edu_turma_professor
            (tup_tur_id, tup_fun_id)
            WHERE tup_dis_id IS NULL AND tup_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('edu_turma_professor')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS turma_professor_aee_unica');

        Schema::table('edu_turma_professor', function (Blueprint $table) {
            $table->unsignedBigInteger('tup_dis_id')->nullable(false)->change();
        });
    }
};
