<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_aluno_saude', function (Blueprint $t) {
            $t->bigIncrements('als_id');
            $t->unsignedBigInteger('als_aln_id');
            $t->string('als_tipo_sanguineo', 3)->nullable();
            $t->text('als_ds_alergias')->nullable();
            $t->boolean('als_fl_pcd')->default(false);
            $t->timestamp('als_created_at')->nullable();
            $t->timestamp('als_updated_at')->nullable();

            $t->foreign('als_aln_id')
                ->references('aln_id')
                ->on('edu_aluno')
                ->cascadeOnDelete();

            $t->unique('als_aln_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_aluno_saude');
    }
};
