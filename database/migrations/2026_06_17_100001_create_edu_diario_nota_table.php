<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_diario_nota', function (Blueprint $t) {
            $t->bigIncrements('nta_id');

            $t->unsignedBigInteger('nta_ava_id');
            $t->unsignedBigInteger('nta_aln_id');
            $t->decimal('nta_valor', 4, 2)->nullable();

            $t->timestamp('nta_created_at')->nullable();
            $t->timestamp('nta_updated_at')->nullable();
            $t->softDeletes('nta_deleted_at');

            $t->foreign('nta_ava_id')->references('ava_id')->on('edu_diario_avaliacao')->cascadeOnDelete();
            $t->foreign('nta_aln_id')->references('aln_id')->on('edu_aluno')->restrictOnDelete();

            $t->index('nta_ava_id');
        });

        // Uma nota por aluno por avaliação (ignorando soft-deletes).
        DB::statement('CREATE UNIQUE INDEX edu_diario_nota_ava_aln_ux ON edu_diario_nota (nta_ava_id, nta_aln_id) WHERE nta_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_diario_nota');
    }
};
