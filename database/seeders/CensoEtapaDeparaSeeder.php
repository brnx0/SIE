<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * De-para etapa Educacenso → série do SIE, com o mapeamento FIXADO por nome de
 * série (resolve o ser_id no seed, robusto a ids diferentes entre ambientes).
 * O segmento sai da própria série (edu_serie.seg_id) no commit.
 * Idempotente (upsert por ced_codigo). Ver docs/regras/importacao_censo.md.
 */
class CensoEtapaDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // [codigo, descricao, ignorar, nome_serie|null]
        $etapas = [
            [0,  'Sem etapa (atividade complementar / AEE)', true, null],
            [1,  'Educação Infantil - Creche', false, 'GRUPO 01'],
            [2,  'Educação Infantil - Pré-escola', false, 'BERCARIO'],
            [14, 'Ensino Fundamental de 9 anos - 1º ano', false, '1º ANO'],
            [15, 'Ensino Fundamental de 9 anos - 2º ano', false, '2º ANO'],
            [16, 'Ensino Fundamental de 9 anos - 3º ano', false, '3º ANO'],
            [17, 'Ensino Fundamental de 9 anos - 4º ano', false, '4º ANO'],
            [18, 'Ensino Fundamental de 9 anos - 5º ano', false, '5º ANO'],
            [19, 'Ensino Fundamental de 9 anos - 6º ano', false, '6º ANO'],
            [20, 'Ensino Fundamental de 9 anos - 7º ano', false, '7º ANO'],
            [21, 'Ensino Fundamental de 9 anos - 8º ano', false, '8º ANO'],
            [22, 'Ensino Fundamental de 9 anos - 9º ano', false, '9º ANO'],
            [41, 'Ensino Médio - 1ª série', false, null],
            [42, 'Ensino Médio - 2ª série', false, null],
            [43, 'Ensino Médio - 3ª série', false, null],
            [44, 'Ensino Médio - 4ª série', false, null],
            [69, 'EJA - Ensino Fundamental - Anos Iniciais', false, 'TAP I'],
            [70, 'EJA - Ensino Fundamental - Anos Finais', false, null],
            [72, 'EJA - Ensino Médio', false, null],
        ];

        foreach ($etapas as [$codigo, $descricao, $ignorar, $nomeSerie]) {
            $serId = null;

            if ($nomeSerie) {
                $serId = DB::table('edu_serie')->where('ser_nome', $nomeSerie)->value('ser_id');

                if (! $serId) {
                    // Série esperada não encontrada em edu_serie (nome divergente ou tabela
                    // ainda não populada). Gravaria ced_ser_id = NULL e bloquearia as turmas
                    // dessa etapa no commit — avisa em vez de falhar em silêncio.
                    // Rode o SerieSeeder antes e reexecute este seeder.
                    $this->command?->warn(
                        "CensoEtapaDeparaSeeder: série '{$nomeSerie}' (etapa {$codigo}) não encontrada em edu_serie — ced_ser_id ficará NULL."
                    );
                }
            }

            DB::table('cfg_censo_etapa_depara')->updateOrInsert(
                ['ced_codigo' => $codigo],
                [
                    'ced_descricao'  => $descricao,
                    'ced_fl_ignorar' => $ignorar,
                    'ced_ser_id'     => $serId,
                    'ced_updated_at' => $now,
                    'ced_created_at' => $now,
                ]
            );
        }
    }
}
