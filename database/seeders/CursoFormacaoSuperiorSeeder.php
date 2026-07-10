<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CursoFormacaoSuperiorSeeder extends Seeder
{
    /**
     * Tabela auxiliar do Censo Escolar: Cursos de Formação Superior 2026 (INEP).
     * Fonte: database/seeders/data/cursos_formacao_superior_2026.json.
     * Idempotente — upsert pela chave cfs_codigo.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/cursos_formacao_superior_2026.json');

        if (! is_file($path)) {
            $this->command?->warn("Arquivo de cursos não encontrado: {$path}");

            return;
        }

        $cursos = json_decode(file_get_contents($path), true) ?: [];
        $now = now();

        $rows = array_map(fn ($c) => [
            'cfs_codigo'         => $c['codigo'],
            'cfs_nome'           => mb_substr($c['nome'], 0, 255),
            'cfs_grau'           => $c['grau'] ?? null,
            'cfs_area_geral'     => $c['area_geral'] ?? null,
            'cfs_area_detalhada' => $c['area_detalhada'] ?? null,
            'cfs_fl_ativo'       => true,
            'cfs_created_at'     => $now,
            'cfs_updated_at'     => $now,
        ], $cursos);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('edu_curso_formacao_superior')->upsert(
                $chunk,
                ['cfs_codigo'],
                ['cfs_nome', 'cfs_grau', 'cfs_area_geral', 'cfs_area_detalhada', 'cfs_updated_at'],
            );
        }
    }
}
