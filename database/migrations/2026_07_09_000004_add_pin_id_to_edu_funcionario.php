<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->unsignedBigInteger('fun_pin_id')->nullable()->after('fun_povo_indigena');

            $t->foreign('fun_pin_id')
                ->references('pin_id')
                ->on('edu_povo_indigena')
                ->restrictOnDelete();

            $t->index('fun_pin_id');
        });
    }

    public function down(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropForeign(['fun_pin_id']);
            $t->dropIndex(['fun_pin_id']);
            $t->dropColumn('fun_pin_id');
        });
    }
};
