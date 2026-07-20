<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_ponto', function (Blueprint $table) {
            $table->bigIncrements('pto_id');
            $table->unsignedBigInteger('pto_rot_id');
            $table->smallInteger('pto_ordem'); // sequência no trajeto (sem unique p/ não travar reorder)
            $table->string('pto_nome', 120);
            $table->string('pto_endereco', 200)->nullable();
            $table->unsignedBigInteger('pto_bai_id')->nullable();
            $table->decimal('pto_latitude', 10, 7)->nullable();
            $table->decimal('pto_longitude', 10, 7)->nullable();
            $table->time('pto_hr_prevista')->nullable();
            $table->unsignedBigInteger('pto_tin_id')->nullable(); // quando o ponto é uma instituição
            $table->timestamp('pto_created_at')->nullable();
            $table->timestamp('pto_updated_at')->nullable();
            $table->softDeletesTz('pto_deleted_at');

            $table->foreign('pto_rot_id')->references('rot_id')->on('edu_transporte_rota');
            $table->foreign('pto_bai_id')->references('bai_id')->on('edu_bairro');
            $table->foreign('pto_tin_id')->references('tin_id')->on('edu_transporte_instituicao');
            $table->index('pto_rot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_ponto');
    }
};
