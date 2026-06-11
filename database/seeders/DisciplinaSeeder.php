<?php

namespace Database\Seeders;

use App\Models\Disciplina\Disciplina;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DisciplinaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = DB::table('edu_area_conhecimento')
            ->pluck('arc_id', 'arc_nome');

        $cienciasNatureza = $areas['Ciências da Natureza, Matemática e suas Tecnologias'] ?? null;
        $linguagem        = $areas['Linguagem, Códigos e suas Tecnologias'] ?? null;
        $humanas          = $areas['Ciências Humanas e suas Tecnologias'] ?? null;
        $mundo            = $areas['Conhecimento de Mundo'] ?? null;

        $disciplinas = [
            // Ciências da Natureza, Matemática e suas Tecnologias
            ['CIÊNCIAS',         $cienciasNatureza],
            ['MATEMÁTICA',       $cienciasNatureza],

            // Linguagem, Códigos e suas Tecnologias
            ['LÍNGUA PORTUGUESA', $linguagem],
            ['LÍNGUA INGLESA',    $linguagem],
            ['EDUCAÇÃO FÍSICA',   $linguagem],
            ['ARTES',             $linguagem],

            // Ciências Humanas e suas Tecnologias
            ['GEOGRAFIA',        $humanas],
            ['HISTÓRIA',         $humanas],
            ['ENSINO RELIGIOSO', $humanas],

            // Conhecimento de Mundo (BNCC Educação Infantil)
            ['O EU, O OUTRO E NÓS',                          $mundo],
            ['CORPO, GESTOS E MOVIMENTOS',                   $mundo],
            ['TRAÇOS, SONS, CORES E FORMAS',                 $mundo],
            ['ESCUTA, FALA, PENSAMENTO E IMAGINAÇÃO',        $mundo],
            ['TEMPO, QUANTIDADES, RELAÇÕES E TRANSFORMAÇÕES', $mundo],
        ];

        foreach ($disciplinas as [$nome, $arcId]) {
            if (! $arcId) {
                $this->command?->warn("Área de conhecimento não encontrada para '{$nome}' — pulando.");
                continue;
            }

            Disciplina::updateOrCreate(
                ['dis_nome' => $nome],
                [
                    'arc_id'       => $arcId,
                    'dis_nome_mec' => $nome,
                    'dis_fl_ativo' => true,
                ],
            );
        }
    }
}
