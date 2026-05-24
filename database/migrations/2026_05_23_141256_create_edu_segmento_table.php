<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_segmento', function (Blueprint $t) {
            $t->bigIncrements('seg_id');

            $t->char('seg_cd_inep', 8)->nullable()->unique();
            $t->string('seg_nome_reduzido', 60);
            $t->string('seg_nome_completo', 150);
            $t->unsignedTinyInteger('seg_qt_anos_escolares');
            $t->unsignedTinyInteger('seg_ordem');
            $t->date('seg_dt_abertura');
            $t->date('seg_dt_encerramento')->nullable();
            $t->boolean('seg_fl_prereq')->default(false);
            $t->text('seg_ds_prereq')->nullable();
            $t->text('seg_observacoes')->nullable();

            $t->boolean('seg_fl_ativo')->default(true);
            $t->timestamp('seg_created_at')->nullable();
            $t->timestamp('seg_updated_at')->nullable();
            $t->softDeletes('seg_deleted_at');

            $t->index('seg_nome_reduzido');
            $t->index('seg_ordem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_segmento');
    }
};
