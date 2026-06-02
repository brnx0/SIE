<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno', function (Blueprint $table) {
            $table->string('aln_nis', 11)->nullable()->after('aln_nr_certidao');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno', function (Blueprint $table) {
            $table->dropColumn('aln_nis');
        });
    }
};
