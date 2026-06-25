<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dias letivos passam a ser ÚNICOS por ano letivo (toda a rede), sem segmento.
 * Colapsa os registros por segmento em um único por ano e remove dlt_seg_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mantém apenas 1 registro por ano (o de menor id) e remove os demais.
        DB::statement('DELETE FROM cfg_dias_letivos a USING cfg_dias_letivos b WHERE a.dlt_anl_id = b.dlt_anl_id AND a.dlt_id > b.dlt_id');

        // Remove FK e unique que envolvem dlt_seg_id (nomes podem variar entre ambientes).
        DB::statement(<<<'SQL'
DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN
        SELECT conname FROM pg_constraint
        WHERE conrelid = 'cfg_dias_letivos'::regclass
          AND contype IN ('f', 'u')
          AND pg_get_constraintdef(oid) ILIKE '%dlt_seg_id%'
    LOOP
        EXECUTE 'ALTER TABLE cfg_dias_letivos DROP CONSTRAINT ' || quote_ident(r.conname);
    END LOOP;
END$$;
SQL);

        Schema::table('cfg_dias_letivos', function (Blueprint $t) {
            $t->dropColumn('dlt_seg_id');
            $t->unique('dlt_anl_id');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_dias_letivos', function (Blueprint $t) {
            $t->dropUnique(['dlt_anl_id']);
            $t->unsignedBigInteger('dlt_seg_id')->nullable()->after('dlt_anl_id');
        });
    }
};
