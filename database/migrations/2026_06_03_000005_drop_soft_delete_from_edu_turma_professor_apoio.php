<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('edu_turma_professor_apoio', 'tpa_deleted_at')) {
            return;
        }

        // Limpa registros soft-deleted existentes (estão ocupando o unique)
        DB::table('edu_turma_professor_apoio')->whereNotNull('tpa_deleted_at')->delete();

        Schema::table('edu_turma_professor_apoio', function (Blueprint $t) {
            $t->dropColumn('tpa_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_professor_apoio', function (Blueprint $t) {
            $t->timestamp('tpa_deleted_at')->nullable();
        });
    }
};
