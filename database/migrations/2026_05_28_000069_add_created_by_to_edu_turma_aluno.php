<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->unsignedBigInteger('tma_created_by_id')->nullable()->after('tma_deleted_at');
            $table->foreign('tma_created_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->dropForeign(['tma_created_by_id']);
            $table->dropColumn('tma_created_by_id');
        });
    }
};
