<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->string('aln_nome_social', 100)->nullable()->after('aln_nome');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $t) {
            $t->dropColumn('aln_nome_social');
        });
    }
};
