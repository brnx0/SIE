<?php

namespace Database\Seeders;

use App\Models\Diario\InstrumentoAvaliativo;
use Illuminate\Database\Seeder;

class InstrumentoAvaliativoSeeder extends Seeder
{
    public function run(): void
    {
        $instrumentos = [
            ['iav_nome' => 'Prova',                'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Trabalho em Grupo',    'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Trabalho individual',  'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Observação',           'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Prova de Recuperação', 'iav_fl_recuperacao' => true],
        ];

        foreach ($instrumentos as $i) {
            InstrumentoAvaliativo::updateOrCreate(
                ['iav_nome' => $i['iav_nome']],
                [
                    'iav_fl_ativo'       => true,
                    'iav_fl_recuperacao' => $i['iav_fl_recuperacao'],
                ],
            );
        }
    }
}
