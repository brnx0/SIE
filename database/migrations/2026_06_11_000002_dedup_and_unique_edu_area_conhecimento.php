<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Para cada arc_nome, mantém menor arc_id e repointa FKs dos duplicados.
        $duplicados = DB::select("
            SELECT arc_nome, MIN(arc_id) AS keep_id, ARRAY_AGG(arc_id) AS all_ids
            FROM edu_area_conhecimento
            GROUP BY arc_nome
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicados as $row) {
            $keep = (int) $row->keep_id;
            $allIds = is_string($row->all_ids)
                ? array_map('intval', explode(',', trim($row->all_ids, '{}')))
                : array_map('intval', (array) $row->all_ids);
            $remover = array_values(array_diff($allIds, [$keep]));

            if (empty($remover)) continue;

            DB::table('edu_disciplina')
                ->whereIn('arc_id', $remover)
                ->update(['arc_id' => $keep]);

            DB::table('edu_area_conhecimento')
                ->whereIn('arc_id', $remover)
                ->delete();
        }

        // 2. Cria UNIQUE para impedir duplicação futura.
        Schema::table('edu_area_conhecimento', function (Blueprint $table) {
            $table->unique('arc_nome', 'edu_area_conhecimento_nome_unique');
        });
    }

    public function down(): void
    {
        Schema::table('edu_area_conhecimento', function (Blueprint $table) {
            $table->dropUnique('edu_area_conhecimento_nome_unique');
        });
    }
};
