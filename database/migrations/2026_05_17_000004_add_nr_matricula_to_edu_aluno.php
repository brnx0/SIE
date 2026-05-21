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
            $t->unsignedBigInteger('aln_nr_matricula')->nullable()->after('aln_cd_inep');
        });

        // Único entre não-excluídos.
        DB::statement('CREATE UNIQUE INDEX edu_aluno_aln_nr_matricula_unique ON edu_aluno (aln_nr_matricula) WHERE aln_nr_matricula IS NOT NULL AND aln_deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS edu_aluno_aln_nr_matricula_unique');
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->dropColumn('aln_nr_matricula');
        });
    }
};
