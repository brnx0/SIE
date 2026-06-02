<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->boolean('tma_fl_trouxe_transferencia')->default(false)->after('tma_obs');
            $table->boolean('tma_fl_trouxe_rg')->default(false)->after('tma_fl_trouxe_transferencia');
            $table->boolean('tma_fl_trouxe_reg_nascimento')->default(false)->after('tma_fl_trouxe_rg');
            $table->boolean('tma_fl_bolsa_familia')->default(false)->after('tma_fl_trouxe_reg_nascimento');
            $table->boolean('tma_fl_recebe_merenda')->default(false)->after('tma_fl_bolsa_familia');
            $table->boolean('tma_fl_usa_transporte')->default(false)->after('tma_fl_recebe_merenda');
            $table->boolean('tma_fl_usa_biblioteca')->default(false)->after('tma_fl_usa_transporte');
            $table->boolean('tma_fl_indigena')->default(false)->after('tma_fl_usa_biblioteca');
            $table->boolean('tma_fl_creche')->default(false)->after('tma_fl_indigena');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            $table->dropColumn([
                'tma_fl_trouxe_transferencia',
                'tma_fl_trouxe_rg',
                'tma_fl_trouxe_reg_nascimento',
                'tma_fl_bolsa_familia',
                'tma_fl_recebe_merenda',
                'tma_fl_usa_transporte',
                'tma_fl_usa_biblioteca',
                'tma_fl_indigena',
                'tma_fl_creche',
            ]);
        });
    }
};
