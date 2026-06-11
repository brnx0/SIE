<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaConhecimentoSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            'Ciências da Natureza, Matemática e suas Tecnologias',
            'Ciências Humanas e suas Tecnologias',
            'Conhecimento de Mundo',
            'Formação Pessoa e Social',
            'Linguagem, Códigos e suas Tecnologias',
        ];

        foreach ($areas as $nome) {
            DB::table('edu_area_conhecimento')->updateOrInsert(
                ['arc_nome' => $nome],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
