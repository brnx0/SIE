<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_cargo', function (Blueprint $t) {
            $t->bigIncrements('crg_id');
            $t->string('crg_nome', 100);
            $t->string('crg_descricao', 255)->nullable();
            $t->boolean('crg_fl_ativo')->default(true);

            $t->timestamp('crg_created_at')->nullable();
            $t->timestamp('crg_updated_at')->nullable();
            $t->softDeletes('crg_deleted_at');

            $t->index('crg_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_cargo');
    }
};
