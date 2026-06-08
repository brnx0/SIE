<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('edu_turma')) {
            return;
        }

        Schema::table('edu_turma', function (Blueprint $table) {
            // Discriminador de modalidade: REGULAR (padrão) | AEE
            if (! Schema::hasColumn('edu_turma', 'tur_modalidade')) {
                $table->string('tur_modalidade', 20)->default('REGULAR')->after('tur_anl_id');
            }

            // Sala / Dependência (SRM) — sem uso ainda, deixada nullable p/ AEE
            if (! Schema::hasColumn('edu_turma', 'tur_aee_sala')) {
                $table->string('tur_aee_sala', 100)->nullable()->after('tur_local_diferenciado');
            }

            // AEE não usa segmento/série → tornar nullable
            $table->unsignedBigInteger('tur_seg_id')->nullable()->change();
            $table->unsignedBigInteger('tur_ser_id')->nullable()->change();
        });

        // Índice de unicidade: regular só vale entre turmas REGULAR;
        // AEE ganha índice próprio (sem série, usa nome+turno).
        DB::statement('DROP INDEX IF EXISTS turma_unica');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS turma_unica ON edu_turma
            (tur_esc_id, tur_anl_id, tur_ser_id, tur_turno, tur_nome)
            WHERE tur_deleted_at IS NULL AND tur_modalidade = 'REGULAR'
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS turma_aee_unica ON edu_turma
            (tur_esc_id, tur_anl_id, tur_turno, tur_nome)
            WHERE tur_deleted_at IS NULL AND tur_modalidade = 'AEE'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('edu_turma')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS turma_aee_unica');
        DB::statement('DROP INDEX IF EXISTS turma_unica');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS turma_unica ON edu_turma
            (tur_esc_id, tur_anl_id, tur_ser_id, tur_turno, tur_nome)
            WHERE tur_deleted_at IS NULL
        ");

        Schema::table('edu_turma', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['tur_modalidade', 'tur_aee_sala'],
                fn ($c) => Schema::hasColumn('edu_turma', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
            // Volta seg/ser a NOT NULL (assume sem linhas com null)
            $table->unsignedBigInteger('tur_seg_id')->nullable(false)->change();
            $table->unsignedBigInteger('tur_ser_id')->nullable(false)->change();
        });
    }
};
