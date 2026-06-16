<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_conceito', function (Blueprint $table) {
            $table->bigIncrements('cnc_id');
            $table->string('cnc_sigla', 10);
            $table->string('cnc_descricao', 100);
            $table->decimal('cnc_limite_inferior', 5, 2);
            $table->decimal('cnc_limite_superior', 5, 2);
            $table->unsignedBigInteger('cnc_created_by_id')->nullable();
            $table->unsignedBigInteger('cnc_updated_by_id')->nullable();
            $table->timestamp('cnc_created_at')->nullable();
            $table->timestamp('cnc_updated_at')->nullable();

            $table->unique('cnc_sigla');

            $table->foreign('cnc_created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cnc_updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_conceito');
    }
};
