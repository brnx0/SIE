<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->unsignedBigInteger('aln_pin_id')->nullable()->after('aln_mun_id_nasc');

            $t->foreign('aln_pin_id')
                ->references('pin_id')
                ->on('edu_povo_indigena')
                ->restrictOnDelete();

            $t->index('aln_pin_id');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->dropForeign(['aln_pin_id']);
            $t->dropIndex(['aln_pin_id']);
            $t->dropColumn('aln_pin_id');
        });
    }
};
