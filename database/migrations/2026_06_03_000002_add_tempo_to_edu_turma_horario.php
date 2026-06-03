<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dropar FK e unique existentes via SQL direto — nomes podem variar por ambiente
        DB::statement("
            DO $$
            DECLARE r RECORD;
            BEGIN
                -- Drop todas FKs de trh_grh_id
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'edu_turma_horario'::regclass
                      AND contype = 'f'
                      AND conname ILIKE '%grh%'
                LOOP
                    EXECUTE 'ALTER TABLE edu_turma_horario DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;

                -- Drop todas unique que envolvam trh_grh_id
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'edu_turma_horario'::regclass
                      AND contype = 'u'
                      AND conname ILIKE '%grh%'
                LOOP
                    EXECUTE 'ALTER TABLE edu_turma_horario DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;
            END$$;
        ");

        Schema::table('edu_turma_horario', function (Blueprint $table) {
            // grh_id vira nullable (retrocompatibilidade)
            $table->unsignedBigInteger('trh_grh_id')->nullable()->change();

            // Novo campo tempo (1–10)
            $table->unsignedTinyInteger('trh_tempo')->nullable()->after('trh_grh_id');

            // Hora do tempo (opcional, ex.: 07:30)
            $table->time('trh_hora')->nullable()->after('trh_tempo');

            // Nova unique por tempo + dia
            $table->unique(['trh_tur_id', 'trh_tempo', 'trh_dia'], 'edu_turma_horario_tur_tempo_dia_unique');
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_horario', function (Blueprint $table) {
            $table->dropUnique('edu_turma_horario_tur_tempo_dia_unique');
            $table->dropColumn(['trh_tempo', 'trh_hora']);
            $table->unsignedBigInteger('trh_grh_id')->nullable(false)->change();
            $table->foreign('trh_grh_id')->references('grh_id')->on('edu_grade_horario')->restrictOnDelete();
            $table->unique(['trh_tur_id', 'trh_grh_id', 'trh_dia']);
        });
    }
};
