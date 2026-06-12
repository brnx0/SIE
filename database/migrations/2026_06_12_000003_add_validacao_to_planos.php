<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->unsignedBigInteger('dpa_validado_por_user_id')->nullable()->after('dpa_obs_coordenador');
            $table->timestamp('dpa_validado_em')->nullable()->after('dpa_validado_por_user_id');
            $table->foreign('dpa_validado_por_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('edu_diario_plano_aee', function (Blueprint $table) {
            $table->unsignedBigInteger('dae_validado_por_user_id')->nullable()->after('dae_obs_coordenador');
            $table->timestamp('dae_validado_em')->nullable()->after('dae_validado_por_user_id');
            $table->foreign('dae_validado_por_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_diario_plano_aula', function (Blueprint $table) {
            $table->dropForeign(['dpa_validado_por_user_id']);
            $table->dropColumn(['dpa_validado_por_user_id', 'dpa_validado_em']);
        });
        Schema::table('edu_diario_plano_aee', function (Blueprint $table) {
            $table->dropForeign(['dae_validado_por_user_id']);
            $table->dropColumn(['dae_validado_por_user_id', 'dae_validado_em']);
        });
    }
};
