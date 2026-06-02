<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->unsignedBigInteger('mat_created_by_id')->nullable()->after('mat_deleted_at');
            $table->foreign('mat_created_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->dropForeign(['mat_created_by_id']);
            $table->dropColumn('mat_created_by_id');
        });
    }
};
