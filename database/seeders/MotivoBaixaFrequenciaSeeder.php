<?php

namespace Database\Seeders;

use App\Models\Parametro\MotivoBaixaFrequencia;
use Illuminate\Database\Seeder;

class MotivoBaixaFrequenciaSeeder extends Seeder
{
    public function run(): void
    {
        // Código = PK (gerado automaticamente). Ordem de inserção define a sequência.
        $descricoes = [
            'Tratamento de doença e de atenção à saúde do estudante',
            'Doença/óbito na família',
            'Fatos que impedem o deslocamento/acesso do estudante à escola',
            'Suspensão escolar',
            'Participação em atividade extraclasse/atividades híbridas (semipresenciais)',
            'Preconceito/Discriminação no ambiente escolar/bullying',
            'Ausência às aulas por respeito às questões sociais, culturais, étnicas ou religiosas',
            'Gravidez',
            'Situação de rua',
            'Trabalho infantil',
            'Violência no ambiente escolar',
            'Trabalho do Adolescente',
            'Exploração/Abuso Sexual',
            'Desinteresse/Desmotivação pelos estudos',
            'Abandono Escolar/Desistência',
            'Questões socioeconômicas, educacionais e/ou familiares',
            'Envolvimento com drogas',
            'Envolvimento em atos infracionais',
            'Violência Intrafamiliar',
            'Óbito do estudante',
            'Outros',
        ];

        foreach ($descricoes as $descricao) {
            MotivoBaixaFrequencia::firstOrCreate(
                ['mbf_descricao' => $descricao],
                ['mbf_fl_ativo' => true],
            );
        }
    }
}
