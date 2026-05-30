<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Apaga linhas previamente soft-deleted (não há mais conceito de soft delete)
        DB::table('edu_grade_disciplinar')->whereNotNull('grd_deleted_at')->delete();

        // Substitui unique parcial por unique full (sem soft delete não precisa de parcial)
        DB::statement('DROP INDEX IF EXISTS grd_unica_idx');

        Schema::table('edu_grade_disciplinar', function (Blueprint $t) {
            $t->dropColumn('grd_deleted_at');
            $t->unique(['grd_anl_id', 'grd_seg_id', 'grd_ser_id', 'grd_dis_id'], 'grd_unica_idx');
        });
    }

    public function down(): void
    {
        Schema::table('edu_grade_disciplinar', function (Blueprint $t) {
            $t->dropUnique('grd_unica_idx');
            $t->softDeletes('grd_deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX grd_unica_idx ON edu_grade_disciplinar (grd_anl_id, grd_seg_id, grd_ser_id, grd_dis_id) WHERE grd_deleted_at IS NULL');
    }
};
