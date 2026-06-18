<?php

namespace Database\Seeders;

use App\Models\Parametro\Conceito;
use Illuminate\Database\Seeder;

class ConceitoSeeder extends Seeder
{
    public function run(): void
    {
        $conceitos = [
            ['cnc_sigla' => 'I', 'cnc_descricao' => 'INSUFICIENTE', 'cnc_limite_inferior' => 0.00, 'cnc_limite_superior' => 4.90, 'cnc_peso' => 1],
            ['cnc_sigla' => 'S', 'cnc_descricao' => 'SUFICIENTE',   'cnc_limite_inferior' => 5.00, 'cnc_limite_superior' => 8.10, 'cnc_peso' => 2],
            ['cnc_sigla' => 'H', 'cnc_descricao' => 'HABILITADO',   'cnc_limite_inferior' => 8.20, 'cnc_limite_superior' => 10.00, 'cnc_peso' => 3],
        ];

        foreach ($conceitos as $c) {
            Conceito::updateOrCreate(
                ['cnc_sigla' => $c['cnc_sigla']],
                [
                    'cnc_descricao'       => $c['cnc_descricao'],
                    'cnc_limite_inferior' => $c['cnc_limite_inferior'],
                    'cnc_limite_superior' => $c['cnc_limite_superior'],
                    'cnc_peso'            => $c['cnc_peso'],
                ],
            );
        }
    }
}
