<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno_movimentacao', function (Blueprint $t) {
            $t->timestamp('mva_cancelada_at')->nullable()->after('mva_cancelada_motivo');
            $t->unsignedBigInteger('mva_cancelada_user_id')->nullable()->after('mva_cancelada_at');
            $t->foreign('mva_cancelada_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno_movimentacao', function (Blueprint $t) {
            $t->dropForeign(['mva_cancelada_user_id']);
            $t->dropColumn(['mva_cancelada_at', 'mva_cancelada_user_id']);
        });
    }
};
