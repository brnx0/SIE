<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_media_escola', function (Blueprint $table) {
            $table->bigIncrements('mde_id');
            $table->unsignedBigInteger('mde_anl_id');
            $table->unsignedBigInteger('mde_esc_id');
            $table->decimal('mde_media', 4, 2);
            $table->unsignedBigInteger('mde_created_by_id')->nullable();
            $table->unsignedBigInteger('mde_updated_by_id')->nullable();
            $table->timestamp('mde_created_at')->nullable();
            $table->timestamp('mde_updated_at')->nullable();

            // Uma escola tem no máximo uma média específica por ano letivo
            $table->unique(['mde_anl_id', 'mde_esc_id']);

            $table->foreign('mde_anl_id')->references('anl_id')->on('cfg_ano_letivo')->cascadeOnDelete();
            $table->foreign('mde_esc_id')->references('esc_id')->on('edu_escola')->cascadeOnDelete();
            $table->foreign('mde_created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mde_updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_media_escola');
    }
};
