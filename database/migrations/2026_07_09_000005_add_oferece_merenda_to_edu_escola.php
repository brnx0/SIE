<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_escola', function (Blueprint $t) {
            $t->boolean('esc_fl_oferece_merenda')->default(false)->after('esc_fl_sorteio_vagas');
        });
    }

    public function down(): void
    {
        Schema::table('edu_escola', function (Blueprint $t) {
            $t->dropColumn('esc_fl_oferece_merenda');
        });
    }
};
