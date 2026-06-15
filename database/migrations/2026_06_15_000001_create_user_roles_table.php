<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });

        // Migrate existing role data
        DB::statement("
            INSERT INTO user_roles (user_id, role, created_at, updated_at)
            SELECT id, role, NOW(), NOW()
            FROM users
            WHERE role IS NOT NULL AND role != ''
        ");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('aluno')->after('password');
        });

        DB::statement("
            UPDATE users
            SET role = (
                SELECT ur.role FROM user_roles ur
                WHERE ur.user_id = users.id
                ORDER BY ur.id
                LIMIT 1
            )
        ");

        Schema::dropIfExists('user_roles');
    }
};
