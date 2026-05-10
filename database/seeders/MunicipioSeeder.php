<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MunicipioSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('edu_municipio')->count() >= 5500) {
            $this->command?->info('edu_municipio já populada — pulando seeder.');
            return;
        }

        $this->command?->info('Buscando municípios IBGE...');

        $response = Http::timeout(60)
            ->withOptions(['verify' => false])
            ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios');

        if (! $response->successful()) {
            $this->command?->error('Falha ao buscar IBGE: HTTP '.$response->status());
            return;
        }

        $now = Carbon::now();
        $rows = [];

        foreach ($response->json() as $m) {
            $codigo = (string) ($m['id'] ?? '');
            if (strlen($codigo) !== 7) {
                continue;
            }

            $rows[] = [
                'mun_codigo_ibge' => $codigo,
                'mun_nome' => $m['nome'] ?? '',
                'mun_uf' => $m['microrregiao']['mesorregiao']['UF']['sigla']
                    ?? $m['regiao-imediata']['regiao-intermediaria']['UF']['sigla']
                    ?? '',
                'mun_created_at' => $now,
                'mun_updated_at' => $now,
            ];
        }

        $this->command?->info('Inserindo '.count($rows).' municípios...');

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('edu_municipio')->upsert(
                $chunk,
                ['mun_codigo_ibge'],
                ['mun_nome', 'mun_uf', 'mun_updated_at'],
            );
        }

        $this->command?->info('Concluído.');
    }
}
