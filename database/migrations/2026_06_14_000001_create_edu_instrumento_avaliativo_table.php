<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_instrumento_avaliativo', function (Blueprint $table) {
            $table->bigIncrements('iav_id');
            $table->string('iav_nome', 100);
            $table->boolean('iav_fl_ativo')->default(true);
            $table->timestamp('iav_created_at')->nullable();
            $table->timestamp('iav_updated_at')->nullable();

            $table->unique('iav_nome', 'edu_instrumento_avaliativo_nome_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_instrumento_avaliativo');
    }
};
