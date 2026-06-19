<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo/metodologia do diário passa a ser POR DISCIPLINA (não mais 1 por dia).
 * Acrescenta vínculo ao planejamento executado (plano de aula) e amplia os campos
 * de texto (espelham o conteúdo do plano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_diario_conteudo', function (Blueprint $t) {
            $t->unsignedBigInteger('dco_dis_id')->nullable()->after('dco_tur_id');
            $t->boolean('dco_fl_plano_executado')->default(false)->after('dco_metodologia');
            $t->unsignedBigInteger('dco_dpa_id')->nullable()->after('dco_fl_plano_executado');

            $t->foreign('dco_dis_id')->references('dis_id')->on('edu_disciplina')->restrictOnDelete();
            $t->foreign('dco_dpa_id')->references('dpa_id')->on('edu_diario_plano_aula')->nullOnDelete();
        });

        // Texto longo (espelha o conteúdo do plano de aula).
        DB::statement('ALTER TABLE edu_diario_conteudo ALTER COLUMN dco_conteudo TYPE text');
        DB::statement('ALTER TABLE edu_diario_conteudo ALTER COLUMN dco_metodologia TYPE text');

        // Unicidade agora por (turma, data, disciplina).
        DB::statement('DROP INDEX IF EXISTS edu_dco_unico');
        DB::statement('
            CREATE UNIQUE INDEX edu_dco_unico ON edu_diario_conteudo
            (dco_tur_id, dco_dt, dco_dis_id)
            WHERE dco_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS edu_dco_unico');
        DB::statement('
            CREATE UNIQUE INDEX edu_dco_unico ON edu_diario_conteudo
            (dco_tur_id, dco_dt, dco_user_id)
            WHERE dco_deleted_at IS NULL
        ');

        Schema::table('edu_diario_conteudo', function (Blueprint $t) {
            $t->dropForeign(['dco_dis_id']);
            $t->dropForeign(['dco_dpa_id']);
            $t->dropColumn(['dco_dis_id', 'dco_fl_plano_executado', 'dco_dpa_id']);
        });

        DB::statement('ALTER TABLE edu_diario_conteudo ALTER COLUMN dco_conteudo TYPE varchar(255)');
        DB::statement('ALTER TABLE edu_diario_conteudo ALTER COLUMN dco_metodologia TYPE varchar(255)');
    }
};
