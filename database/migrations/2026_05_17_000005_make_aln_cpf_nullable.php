<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->char('aln_cpf', 11)->nullable()->change();
        });

        // Substitui índice único padrão por índice parcial — permite múltiplos NULL.
        DB::statement('ALTER TABLE edu_aluno DROP CONSTRAINT IF EXISTS edu_aluno_aln_cpf_unique');
        DB::statement('DROP INDEX IF EXISTS edu_aluno_aln_cpf_unique');
        DB::statement('CREATE UNIQUE INDEX edu_aluno_aln_cpf_unique ON edu_aluno (aln_cpf) WHERE aln_cpf IS NOT NULL AND aln_deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS edu_aluno_aln_cpf_unique');
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->char('aln_cpf', 11)->nullable(false)->change();
            $t->unique('aln_cpf', 'edu_aluno_aln_cpf_unique');
        });
    }
};
