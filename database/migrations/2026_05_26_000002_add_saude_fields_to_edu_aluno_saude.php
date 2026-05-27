<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_aluno_saude', function (Blueprint $table) {
            // Emergência
            $table->string('als_contato_emergencia', 150)->nullable()->after('als_fl_pcd');
            $table->string('als_telefone_emergencia', 20)->nullable()->after('als_contato_emergencia');
            $table->string('als_plano_saude', 100)->nullable()->after('als_telefone_emergencia');
            $table->string('als_cartao_sus', 20)->nullable()->after('als_plano_saude');

            // Saúde básica
            $table->text('als_alergia_a')->nullable()->after('als_cartao_sus');
            $table->string('als_remedio_febre', 200)->nullable()->after('als_alergia_a');
            $table->string('als_remedio_cefaleia', 200)->nullable()->after('als_remedio_febre');

            // Patologias
            $table->json('als_patologias')->nullable()->after('als_remedio_cefaleia');
            $table->text('als_outra_doenca')->nullable()->after('als_patologias');

            // Patologias infância
            $table->json('als_patologias_infancia')->nullable()->after('als_outra_doenca');
            $table->text('als_outra_doenca_infancia')->nullable()->after('als_patologias_infancia');

            // Deficiência / Transtornos
            $table->json('als_deficiencias')->nullable()->after('als_outra_doenca_infancia');
            $table->json('als_transtornos_globais')->nullable()->after('als_deficiencias');
            $table->json('als_transtornos_aprendizagem')->nullable()->after('als_transtornos_globais');
            $table->text('als_deficiencia_outro')->nullable()->after('als_transtornos_aprendizagem');
            $table->boolean('als_fl_altas_habilidades')->default(false)->after('als_deficiencia_outro');
            $table->string('als_cid', 20)->nullable()->after('als_fl_altas_habilidades');
            $table->text('als_observacao')->nullable()->after('als_cid');

            // Clínica
            $table->json('als_clinicas')->nullable()->after('als_observacao');

            // Recursos INEP
            $table->json('als_recursos_inep')->nullable()->after('als_clinicas');
        });
    }

    public function down(): void
    {
        Schema::table('edu_aluno_saude', function (Blueprint $table) {
            $table->dropColumn([
                'als_contato_emergencia', 'als_telefone_emergencia', 'als_plano_saude', 'als_cartao_sus',
                'als_alergia_a', 'als_remedio_febre', 'als_remedio_cefaleia',
                'als_patologias', 'als_outra_doenca',
                'als_patologias_infancia', 'als_outra_doenca_infancia',
                'als_deficiencias', 'als_transtornos_globais', 'als_transtornos_aprendizagem',
                'als_deficiencia_outro', 'als_fl_altas_habilidades', 'als_cid', 'als_observacao',
                'als_clinicas', 'als_recursos_inep',
            ]);
        });
    }
};
