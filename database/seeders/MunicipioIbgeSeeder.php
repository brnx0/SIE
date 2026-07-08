<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semeia edu_municipio com todos os municípios do IBGE (~5.570).
 * Fonte: database/data/municipios_ibge.json. Idempotente (só insere ausentes).
 */
class MunicipioIbgeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/municipios_ibge.json');
        if (! is_file($path)) {
            throw new RuntimeException("Arquivo não encontrado: {$path}");
        }

        $municipios = json_decode(file_get_contents($path), true);
        if (! is_array($municipios)) {
            throw new RuntimeException('JSON de municípios inválido.');
        }

        $existentes = DB::table('edu_municipio')->pluck('mun_codigo_ibge')->flip();
        $now = now();
        $buffer = [];

        foreach ($municipios as $m) {
            $ibge = (string) $m['codigo_ibge'];
            if ($existentes->has($ibge)) {
                continue;
            }
            $buffer[] = [
                'mun_codigo_ibge' => $ibge,
                'mun_nome'        => $m['nome'],
                'mun_uf'          => $m['uf'],
                'mun_created_at'  => $now,
                'mun_updated_at'  => $now,
            ];
            if (count($buffer) >= 500) {
                DB::table('edu_municipio')->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('edu_municipio')->insert($buffer);
        }
    }
}
