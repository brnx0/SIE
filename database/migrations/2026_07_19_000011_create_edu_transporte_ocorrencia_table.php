<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_transporte_ocorrencia', function (Blueprint $table) {
            $table->bigIncrements('toc_id');
            $table->unsignedBigInteger('toc_tdb_id');
            $table->string('toc_tipo', 30); // atraso|pane|acidente|indisciplina|via_interditada|outro
            $table->time('toc_hr')->nullable();
            $table->text('toc_descricao');
            $table->timestamp('toc_created_at')->nullable();
            $table->timestamp('toc_updated_at')->nullable();

            $table->foreign('toc_tdb_id')->references('tdb_id')->on('edu_transporte_diario');
            $table->index('toc_tdb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_transporte_ocorrencia');
    }
};
