<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move a escolaridade única de edu_funcionario.fun_escolaridade para a nova
     * tabela de histórico edu_funcionario_escolaridade (1 linha por funcionário),
     * depois remove a coluna. Preserva o dado existente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('edu_funcionario', 'fun_escolaridade')) {
            return;
        }

        $now = now();

        DB::table('edu_funcionario')
            ->whereNotNull('fun_escolaridade')
            ->orderBy('fun_id')
            ->chunkById(500, function ($funcionarios) use ($now) {
                $rows = [];
                foreach ($funcionarios as $f) {
                    $rows[] = [
                        'fes_fun_id'     => $f->fun_id,
                        'fes_nivel'      => $f->fun_escolaridade,
                        'fes_ano_inicio' => null,
                        'fes_ano_fim'    => null,
                        'fes_cfs_id'     => null,
                        'fes_created_at' => $now,
                        'fes_updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('edu_funcionario_escolaridade')->insert($rows);
                }
            }, 'fun_id');

        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->dropColumn('fun_escolaridade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('edu_funcionario', 'fun_escolaridade')) {
            Schema::table('edu_funcionario', function (Blueprint $t) {
                $t->unsignedTinyInteger('fun_escolaridade')->nullable();
            });
        }

        // Best-effort: restaura o maior nível registrado por funcionário.
        DB::table('edu_funcionario_escolaridade')
            ->select('fes_fun_id', DB::raw('MAX(fes_nivel) as nivel'))
            ->groupBy('fes_fun_id')
            ->orderBy('fes_fun_id')
            ->chunk(500, function ($niveis) {
                foreach ($niveis as $n) {
                    DB::table('edu_funcionario')
                        ->where('fun_id', $n->fes_fun_id)
                        ->update(['fun_escolaridade' => $n->nivel]);
                }
            });
    }
};
