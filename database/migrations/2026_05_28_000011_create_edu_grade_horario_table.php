<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_grade_horario', function (Blueprint $table) {
            $table->bigIncrements('grh_id');
            $table->unsignedBigInteger('grh_seg_id');
            $table->time('grh_hora');
            $table->smallInteger('grh_ordem');
            $table->timestamp('grh_created_at')->nullable();
            $table->timestamp('grh_updated_at')->nullable();

            $table->foreign('grh_seg_id')->references('seg_id')->on('edu_segmento')->restrictOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX grade_horario_unica ON edu_grade_horario
            (grh_seg_id, grh_hora)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_grade_horario');
    }
};
