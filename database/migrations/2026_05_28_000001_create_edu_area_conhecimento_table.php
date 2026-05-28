<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_area_conhecimento', function (Blueprint $table) {
            $table->increments('arc_id');
            $table->string('arc_nome', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_area_conhecimento');
    }
};
