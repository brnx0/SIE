<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $table) {
            $table->boolean('cen_eqp_notebook')->default(false)->after('cen_eqp_scanner');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_censo_escolar', function (Blueprint $table) {
            $table->dropColumn('cen_eqp_notebook');
        });
    }
};
