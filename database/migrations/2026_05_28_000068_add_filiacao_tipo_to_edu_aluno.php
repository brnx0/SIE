<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $table) {
            $table->char('aln_filiacao_1_tipo', 3)->nullable()->after('aln_filiacao_1');
            $table->char('aln_filiacao_2_tipo', 3)->nullable()->after('aln_filiacao_2');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $table) {
            $table->dropColumn(['aln_filiacao_1_tipo', 'aln_filiacao_2_tipo']);
        });
    }
};
