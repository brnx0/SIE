<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('edu_tipo_movimentacao')
            ->where('tmv_cod', 1)
            ->update(['tmv_fl_ativo' => false]);
    }

    public function down(): void
    {
        DB::table('edu_tipo_movimentacao')
            ->where('tmv_cod', 1)
            ->update(['tmv_fl_ativo' => true]);
    }
};
