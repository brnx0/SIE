<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->boolean('mat_fl_trouxe_transferencia')->default(false)->after('mat_obs');
            $table->boolean('mat_fl_trouxe_rg')->default(false)->after('mat_fl_trouxe_transferencia');
            $table->boolean('mat_fl_trouxe_reg_nascimento')->default(false)->after('mat_fl_trouxe_rg');
            $table->boolean('mat_fl_bolsa_familia')->default(false)->after('mat_fl_trouxe_reg_nascimento');
            $table->boolean('mat_fl_recebe_merenda')->default(false)->after('mat_fl_bolsa_familia');
            $table->boolean('mat_fl_usa_transporte')->default(false)->after('mat_fl_recebe_merenda');
            $table->boolean('mat_fl_usa_biblioteca')->default(false)->after('mat_fl_usa_transporte');
            $table->boolean('mat_fl_indigena')->default(false)->after('mat_fl_usa_biblioteca');
            $table->boolean('mat_fl_creche')->default(false)->after('mat_fl_indigena');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->dropColumn([
                'mat_fl_trouxe_transferencia',
                'mat_fl_trouxe_rg',
                'mat_fl_trouxe_reg_nascimento',
                'mat_fl_bolsa_familia',
                'mat_fl_recebe_merenda',
                'mat_fl_usa_transporte',
                'mat_fl_usa_biblioteca',
                'mat_fl_indigena',
                'mat_fl_creche',
            ]);
        });
    }
};
