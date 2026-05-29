<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_grade_horario', function (Blueprint $table) {
            $table->char('grh_turno', 1)->default('m')->after('grh_seg_id');
        });

        DB::statement('DROP INDEX IF EXISTS grade_horario_unica');
        DB::statement('
            CREATE UNIQUE INDEX grade_horario_unica ON edu_grade_horario
            (grh_seg_id, grh_turno, grh_hora)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS grade_horario_unica');
        DB::statement('
            CREATE UNIQUE INDEX grade_horario_unica ON edu_grade_horario
            (grh_seg_id, grh_hora)
        ');

        Schema::table('edu_grade_horario', function (Blueprint $table) {
            $table->dropColumn('grh_turno');
        });
    }
};
