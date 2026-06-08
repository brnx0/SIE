<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela pode não existir ainda (deploy fresh roda a migration de criação antes;
        // mas guardamos para evitar erro em estados parciais/manuais).
        if (! Schema::hasTable('edu_turma_horario')) {
            return;
        }

        // Dropar FK e unique de trh_grh_id via SQL direto — nomes podem variar por ambiente.
        // to_regclass evita erro se a tabela não existir.
        DB::statement("
            DO \$\$
            DECLARE r RECORD;
            BEGIN
                IF to_regclass('edu_turma_horario') IS NULL THEN
                    RETURN;
                END IF;

                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'edu_turma_horario'::regclass
                      AND contype IN ('f','u')
                      AND conname ILIKE '%grh%'
                LOOP
                    EXECUTE 'ALTER TABLE edu_turma_horario DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;
            END\$\$;
        ");

        Schema::table('edu_turma_horario', function (Blueprint $table) {
            // grh_id vira nullable (retrocompatibilidade)
            $table->unsignedBigInteger('trh_grh_id')->nullable()->change();

            // Novo campo tempo (1–10) — só se ainda não existir
            if (! Schema::hasColumn('edu_turma_horario', 'trh_tempo')) {
                $table->unsignedTinyInteger('trh_tempo')->nullable()->after('trh_grh_id');
            }

            // Hora do tempo (opcional, ex.: 07:30) — só se ainda não existir
            if (! Schema::hasColumn('edu_turma_horario', 'trh_hora')) {
                $table->time('trh_hora')->nullable()->after('trh_tempo');
            }
        });

        // Nova unique por tempo + dia — só se ainda não existir
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conrelid = 'edu_turma_horario'::regclass
                      AND conname = 'edu_turma_horario_tur_tempo_dia_unique'
                ) THEN
                    ALTER TABLE edu_turma_horario
                        ADD CONSTRAINT edu_turma_horario_tur_tempo_dia_unique
                        UNIQUE (trh_tur_id, trh_tempo, trh_dia);
                END IF;
            END\$\$;
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('edu_turma_horario')) {
            return;
        }

        DB::statement('ALTER TABLE edu_turma_horario DROP CONSTRAINT IF EXISTS edu_turma_horario_tur_tempo_dia_unique');

        Schema::table('edu_turma_horario', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['trh_tempo', 'trh_hora'],
                fn ($c) => Schema::hasColumn('edu_turma_horario', $c)
            ));
            if ($cols) {
                $table->dropColumn($cols);
            }
            $table->unsignedBigInteger('trh_grh_id')->nullable(false)->change();
        });

        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conrelid = \'edu_turma_horario\'::regclass
                      AND conname = \'edu_turma_horario_trh_grh_id_foreign\'
                ) THEN
                    ALTER TABLE edu_turma_horario
                        ADD CONSTRAINT edu_turma_horario_trh_grh_id_foreign
                        FOREIGN KEY (trh_grh_id) REFERENCES edu_grade_horario (grh_id) ON DELETE RESTRICT;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conrelid = \'edu_turma_horario\'::regclass
                      AND conname = \'edu_turma_horario_trh_tur_id_trh_grh_id_trh_dia_unique\'
                ) THEN
                    ALTER TABLE edu_turma_horario
                        ADD CONSTRAINT edu_turma_horario_trh_tur_id_trh_grh_id_trh_dia_unique
                        UNIQUE (trh_tur_id, trh_grh_id, trh_dia);
                END IF;
            END$$;
        ');
    }
};
