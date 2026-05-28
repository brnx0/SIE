<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('fun_id')->nullable()->after('esc_id');
            $table->foreign('fun_id')
                  ->references('fun_id')
                  ->on('edu_funcionario')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['fun_id']);
            $table->dropColumn('fun_id');
        });
    }
};
