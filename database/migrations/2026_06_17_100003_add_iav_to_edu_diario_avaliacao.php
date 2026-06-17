<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_avaliacao', function (Blueprint $t) {
            $t->unsignedBigInteger('ava_iav_id')->nullable()->after('ava_uni_id');
            $t->foreign('ava_iav_id')->references('iav_id')->on('edu_instrumento_avaliativo')->restrictOnDelete();
        });

        // Descrição passa a ser complemento opcional (o tipo vem do instrumento).
        DB::statement('ALTER TABLE edu_diario_avaliacao ALTER COLUMN ava_descricao DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('edu_diario_avaliacao', function (Blueprint $t) {
            $t->dropForeign(['ava_iav_id']);
            $t->dropColumn('ava_iav_id');
        });
    }
};
