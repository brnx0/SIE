<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * De-para etapa Educacenso → série do SIE. Descrições/códigos conforme a tabela
 * oficial INEP (grupos 301..306). Mapeamento FIXADO por nome de série (resolve o
 * ser_id no seed — robusto a ids diferentes entre ambientes). Segmento sai da
 * própria série (edu_serie.seg_id) no commit. Idempotente (upsert por ced_codigo).
 * Ver docs/regras/importacao_censo.md.
 */
class CensoEtapaDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // [codigo, descricao, ignorar, nome_serie|null]
        // nome_serie = nome EXATO em edu_serie (do cadastro do cliente). null = não mapeada.
        $etapas = [
            [0,  'Sem etapa (atividade complementar / AEE)', true, null],

            // 301 Educação Infantil
            [1,  'Educação Infantil - Creche (0 a 3 anos)', false, 'GRUPO 01'],
            [2,  'Educação Infantil - Pré-escola (4 e 5 anos)', false, 'BERCARIO'],
            [3,  'Educação Infantil - Unificada (0 a 5 anos)', false, null],

            // 302 Ensino Fundamental (9 anos)
            [14, 'Ensino Fundamental de 9 anos - 1º ano', false, '1º ANO'],
            [15, 'Ensino Fundamental de 9 anos - 2º ano', false, '2º ANO'],
            [16, 'Ensino Fundamental de 9 anos - 3º ano', false, '3º ANO'],
            [17, 'Ensino Fundamental de 9 anos - 4º ano', false, '4º ANO'],
            [18, 'Ensino Fundamental de 9 anos - 5º ano', false, '5º ANO'],
            [19, 'Ensino Fundamental de 9 anos - 6º ano', false, '6º ANO'],
            [20, 'Ensino Fundamental de 9 anos - 7º ano', false, '7º ANO'],
            [21, 'Ensino Fundamental de 9 anos - 8º ano', false, '8º ANO'],
            [41, 'Ensino Fundamental de 9 anos - 9º ano', false, '9º ANO'],

            // 303 Multi e correção de fluxo
            [22, 'Ensino Fundamental de 9 anos - Multi', false, 'EFMULTI'],
            [23, 'Ensino Fundamental de 9 anos - Correção de fluxo', false, null],
            [56, 'Educação Infantil e Ensino Fundamental - Multietapa', false, 'EIMULTI'],

            // 304 Ensino Médio
            [25, 'Ensino Médio - 1ª série', false, null],
            [26, 'Ensino Médio - 2ª série', false, null],
            [27, 'Ensino Médio - 3ª série', false, null],
            [28, 'Ensino Médio - 4ª série', false, null],
            [29, 'Ensino Médio - Não seriada', false, null],

            // 305 Ensino Médio - Normal/Magistério
            [35, 'Ensino Médio - Normal/Magistério - 1ª série', false, null],
            [36, 'Ensino Médio - Normal/Magistério - 2ª série', false, null],
            [37, 'Ensino Médio - Normal/Magistério - 3ª série', false, null],
            [38, 'Ensino Médio - Normal/Magistério - 4ª série', false, null],

            // 306 EJA
            [69, 'EJA - Ensino Fundamental - Anos Iniciais (1º segmento)', false, 'TAP I'],
            [70, 'EJA - Ensino Fundamental - Anos Finais (2º segmento)', false, null],
            [72, 'EJA - Ensino Fundamental - Multietapas', false, 'EJAMULTI I'],
            [71, 'EJA - Ensino Médio (3º segmento)', false, null],
            [74, 'Curso Técnico Integrado na modalidade EJA', false, null],
            [73, 'Curso FIC Integrado na modalidade EJA - Fundamental', false, null],
            [67, 'Curso FIC Integrado na modalidade EJA - Médio', false, null],
        ];

        foreach ($etapas as [$codigo, $descricao, $ignorar, $nomeSerie]) {
            $serId = null;

            if ($nomeSerie) {
                $serId = DB::table('edu_serie')->where('ser_nome', $nomeSerie)->value('ser_id');

                if (! $serId) {
                    // Série esperada não encontrada (nome divergente ou tabela não populada).
                    // Grava ced_ser_id = NULL → bloqueia as turmas dessa etapa no commit.
                    // Rode o SerieSeeder antes e reexecute este seeder.
                    $this->command?->warn(
                        "CensoEtapaDeparaSeeder: série '{$nomeSerie}' (etapa {$codigo}) não encontrada — ced_ser_id ficará NULL."
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
