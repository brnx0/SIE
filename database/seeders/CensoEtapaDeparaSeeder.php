<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia os códigos de etapa Educacenso conhecidos (descrição + fl_ignorar).
 * seg_id/ser_id ficam nulos — o mapeamento p/ segmento/série é por base (tela admin),
 * pois esses cadastros variam por cliente. Idempotente (upsert por ced_codigo).
 */
class CensoEtapaDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // [codigo, descricao, ignorar]
        $etapas = [
            [0,  'Sem etapa (atividade complementar / AEE)', true],
            [1,  'Educação Infantil - Creche', false],
            [2,  'Educação Infantil - Pré-escola', false],
            [14, 'Ensino Fundamental de 9 anos - 1º ano', false],
            [15, 'Ensino Fundamental de 9 anos - 2º ano', false],
            [16, 'Ensino Fundamental de 9 anos - 3º ano', false],
            [17, 'Ensino Fundamental de 9 anos - 4º ano', false],
            [18, 'Ensino Fundamental de 9 anos - 5º ano', false],
            [19, 'Ensino Fundamental de 9 anos - 6º ano', false],
            [20, 'Ensino Fundamental de 9 anos - 7º ano', false],
            [21, 'Ensino Fundamental de 9 anos - 8º ano', false],
            [22, 'Ensino Fundamental de 9 anos - 9º ano', false],
            [41, 'Ensino Médio - 1ª série', false],
            [42, 'Ensino Médio - 2ª série', false],
            [43, 'Ensino Médio - 3ª série', false],
            [44, 'Ensino Médio - 4ª série', false],
            [69, 'EJA - Ensino Fundamental - Anos Iniciais', false],
            [70, 'EJA - Ensino Fundamental - Anos Finais', false],
            [72, 'EJA - Ensino Médio', false],
        ];

        foreach ($etapas as [$codigo, $descricao, $ignorar]) {
            DB::table('cfg_censo_etapa_depara')->updateOrInsert(
                ['ced_codigo' => $codigo],
                [
                    'ced_descricao'  => $descricao,
                    'ced_fl_ignorar' => $ignorar,
                    'ced_updated_at' => $now,
                    'ced_created_at' => $now,
                ]
            );
        }
    }
}
