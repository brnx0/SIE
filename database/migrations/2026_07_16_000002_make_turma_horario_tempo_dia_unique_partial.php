<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A unique (trh_tur_id, trh_tempo, trh_dia) era total: linha soft-deletada continuava
 * ocupando o slot. Por isso o código apagava horários com forceDelete — o que estourava
 * a FK edu_diario_aula.aul_trh_id (RESTRICT) quando havia aula lançada.
 *
 * Tornando-a parcial (padrão já usado em turma_horario_unica, edu_aul_unico e edu_fal_unico),
 * o soft delete libera o slot e preserva a linha como alvo da FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Soft-deletados que colidem com um horário ativo do mesmo slot não sobrevivem
        // ao índice parcial — some o soft delete, vira exclusão real.
        DB::statement('
            DELETE FROM edu_turma_horario h
             WHERE h.trh_deleted_at IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM edu_diario_aula a WHERE a.aul_trh_id = h.trh_id)
               AND EXISTS (
                   SELECT 1 FROM edu_turma_horario o
                    WHERE o.trh_deleted_at IS NULL
                      AND o.trh_tur_id = h.trh_tur_id
                      AND o.trh_tempo  = h.trh_tempo
                      AND o.trh_dia    = h.trh_dia
               )
        ');

        DB::statement('ALTER TABLE edu_turma_horario DROP CONSTRAINT IF EXISTS edu_turma_horario_tur_tempo_dia_unique');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS edu_turma_horario_tur_tempo_dia_unique
                ON edu_turma_horario (trh_tur_id, trh_tempo, trh_dia)
             WHERE trh_deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS edu_turma_horario_tur_tempo_dia_unique');
        DB::statement('
            ALTER TABLE edu_turma_horario
              ADD CONSTRAINT edu_turma_horario_tur_tempo_dia_unique
              UNIQUE (trh_tur_id, trh_tempo, trh_dia)
        ');
    }
};
