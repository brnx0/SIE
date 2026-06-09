<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cfg_atividade')) {
            return;
        }

        Schema::create('cfg_atividade', function (Blueprint $t) {
            $t->smallIncrements('atv_id');
            $t->string('atv_descricao', 200);
            $t->boolean('atv_fl_ativo')->default(true);
            $t->timestamp('atv_created_at')->nullable();
            $t->timestamp('atv_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_atividade');
    }
};
