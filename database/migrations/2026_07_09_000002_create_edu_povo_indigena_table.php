<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_povo_indigena', function (Blueprint $t) {
            $t->bigIncrements('pin_id');
            $t->unsignedSmallInteger('pin_cd_censo')->unique();
            $t->string('pin_nome', 120);
            $t->timestamp('pin_created_at')->nullable();
            $t->timestamp('pin_updated_at')->nullable();

            $t->index('pin_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_povo_indigena');
    }
};
