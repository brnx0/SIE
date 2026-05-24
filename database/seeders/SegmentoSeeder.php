<?php

namespace Database\Seeders;

use App\Models\Segmento\Segmento;
use Illuminate\Database\Seeder;

class SegmentoSeeder extends Seeder
{
    public function run(): void
    {
        $segmentos = [
            [
                'seg_nome_reduzido'     => 'Creche',
                'seg_nome_completo'     => 'Creche (Grupo 1 ao Grupo 3)',
                'seg_qt_anos_escolares' => 3,
                'seg_ordem'             => 1,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'Pré-escolar',
                'seg_nome_completo'     => 'Pré-Escola (Grupo 4 e 5)',
                'seg_qt_anos_escolares' => 2,
                'seg_ordem'             => 2,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'Ensino Fundamental I',
                'seg_nome_completo'     => 'Ensino Fundamental I (1º ano ao 5º ano)',
                'seg_qt_anos_escolares' => 5,
                'seg_ordem'             => 3,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'Ensino Fundamental II',
                'seg_nome_completo'     => 'Ensino Fundamental II (6º ano ao 9º ano)',
                'seg_qt_anos_escolares' => 4,
                'seg_ordem'             => 4,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'Multi-seriadas',
                'seg_nome_completo'     => 'Multi-seriadas',
                'seg_qt_anos_escolares' => 9,
                'seg_ordem'             => 5,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'EJA I',
                'seg_nome_completo'     => 'EJA I (TAP I, TAP II, TAP III)',
                'seg_qt_anos_escolares' => 3,
                'seg_ordem'             => 6,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'EJA II',
                'seg_nome_completo'     => 'EJA II (TAP IV, TAP V)',
                'seg_qt_anos_escolares' => 2,
                'seg_ordem'             => 7,
                'seg_dt_abertura'       => '2000-01-01',
            ],
            [
                'seg_nome_reduzido'     => 'AEE',
                'seg_nome_completo'     => 'AEE 1º ANO',
                'seg_qt_anos_escolares' => 1,
                'seg_ordem'             => 8,
                'seg_dt_abertura'       => '2000-01-01',
            ],
        ];

        foreach ($segmentos as $data) {
            Segmento::updateOrCreate(
                ['seg_nome_reduzido' => $data['seg_nome_reduzido']],
                $data,
            );
        }
    }
}
