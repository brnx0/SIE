<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstituicaoEnsinoSuperiorSeeder extends Seeder
{
    /**
     * Tabela auxiliar do Censo Escolar: Instituições de Ensino Superior (IES) 2026 (INEP).
     * Fonte: database/seeders/data/instituicoes_ensino_superior_2026.json.
     * Idempotente — upsert pela chave ies_codigo (CO_IES).
     */
    public function run(): void
    {
        $path = database_path('seeders/data/instituicoes_ensino_superior_2026.json');

        if (! is_file($path)) {
            $this->command?->warn("Arquivo de IES não encontrado: {$path}");

            return;
        }

        $ies = json_decode(file_get_contents($path), true) ?: [];
        $now = now();

        $rows = array_map(fn ($i) => [
            'ies_codigo'     => $i['codigo'],
            'ies_nome'       => mb_substr($i['nome'], 0, 255),
            'ies_categoria'  => $i['categoria'] ?? null,
            'ies_municipio'  => $i['municipio'] ?? null,
            'ies_fl_ativo'   => $i['ativo'] ?? true,
            'ies_created_at' => $now,
            'ies_updated_at' => $now,
        ], $ies);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('edu_instituicao_ensino_superior')->upsert(
                $chunk,
                ['ies_codigo'],
                ['ies_nome', 'ies_categoria', 'ies_municipio', 'ies_fl_ativo', 'ies_updated_at'],
            );
        }
    }
}
