<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Regra de negócio mudou: mais de um ano letivo pode estar "em exercício".
        DB::statement('DROP INDEX IF EXISTS cfg_ano_letivo_em_exercicio_unique');
    }

    public function down(): void
    {
        // Recria só se não houver duplicatas (senão a recriação falharia).
        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_class WHERE relname = \'cfg_ano_letivo_em_exercicio_unique\'
                ) THEN
                    CREATE UNIQUE INDEX cfg_ano_letivo_em_exercicio_unique
                        ON cfg_ano_letivo ((1))
                        WHERE anl_fl_em_exercicio = true AND anl_deleted_at IS NULL;
                END IF;
            END$$;
        ');
    }
};
