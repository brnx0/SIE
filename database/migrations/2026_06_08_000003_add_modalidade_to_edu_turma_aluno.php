<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('edu_turma_aluno')) {
            return;
        }

        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            // Modalidade da matrícula (denormalizado da turma) p/ permitir dupla
            // matrícula: regular ATIVA única por ano, AEE livre.
            if (! Schema::hasColumn('edu_turma_aluno', 'tma_modalidade')) {
                $table->string('tma_modalidade', 20)->default('REGULAR')->after('tma_anl_id');
            }
        });

        // Restringe "1 matrícula ATIVA por ano" apenas à modalidade REGULAR.
        DB::statement('DROP INDEX IF EXISTS turma_aluno_ativa_por_ano');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS turma_aluno_ativa_por_ano
            ON edu_turma_aluno (tma_aln_id, tma_anl_id)
            WHERE tma_situacao = 'ATIVA' AND tma_deleted_at IS NULL AND tma_modalidade = 'REGULAR'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('edu_turma_aluno')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS turma_aluno_ativa_por_ano');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS turma_aluno_ativa_por_ano
            ON edu_turma_aluno (tma_aln_id, tma_anl_id)
            WHERE tma_situacao = 'ATIVA' AND tma_deleted_at IS NULL
        ");

        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            if (Schema::hasColumn('edu_turma_aluno', 'tma_modalidade')) {
                $table->dropColumn('tma_modalidade');
            }
        });
    }
};
