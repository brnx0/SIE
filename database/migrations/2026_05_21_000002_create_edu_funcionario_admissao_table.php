<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_funcionario_admissao', function (Blueprint $t) {
            $t->bigIncrements('adm_id');
            $t->unsignedBigInteger('adm_fun_id');
            $t->string('adm_matricula', 30);
            $t->date('adm_dt_admissao');
            $t->unsignedBigInteger('adm_crg_id');
            $t->unsignedTinyInteger('adm_escolaridade_admissao')->nullable();

            $t->timestamp('adm_created_at')->nullable();
            $t->timestamp('adm_updated_at')->nullable();
            $t->softDeletes('adm_deleted_at');

            $t->foreign('adm_fun_id')
                ->references('fun_id')
                ->on('edu_funcionario')
                ->cascadeOnDelete();

            $t->foreign('adm_crg_id')
                ->references('crg_id')
                ->on('edu_cargo')
                ->restrictOnDelete();

            $t->unique(['adm_fun_id', 'adm_matricula']);
            $t->index('adm_fun_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_funcionario_admissao');
    }
};
