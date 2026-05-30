<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_grade_disciplinar', function (Blueprint $t) {
            $t->dropUnique('grd_unica_idx');
        });

        // Unique parcial: só linhas não deletadas contam — permite reinserir após soft delete
        DB::statement('CREATE UNIQUE INDEX grd_unica_idx ON edu_grade_disciplinar (grd_anl_id, grd_seg_id, grd_ser_id, grd_dis_id) WHERE grd_deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS grd_unica_idx');

        Schema::table('edu_grade_disciplinar', function (Blueprint $t) {
            $t->unique(['grd_anl_id', 'grd_seg_id', 'grd_ser_id', 'grd_dis_id'], 'grd_unica_idx');
        });
    }
};
