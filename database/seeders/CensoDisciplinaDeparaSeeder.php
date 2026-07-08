<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * De-para componente curricular Educacenso → disciplina do SIE.
 * Códigos/nomes conforme a Tabela oficial INEP "Áreas do conhecimento /
 * Componentes Curriculares 2025". cdd_dis_id fica nulo até o mapeamento por base
 * (disciplina exige área de conhecimento). Idempotente (upsert por cdd_codigo).
 * Ver docs/regras/importacao_censo.md.
 */
class CensoDisciplinaDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Códigos EXATOS da tabela INEP (não são sequenciais!).
        $componentes = [
            // Linguagens
            6  => 'Língua/Literatura Portuguesa',
            7  => 'Língua/Literatura Estrangeira - Inglês',
            8  => 'Língua/Literatura Estrangeira - Espanhol',
            30 => 'Língua/Literatura Estrangeira - Francês',
            9  => 'Língua/Literatura Estrangeira - Outra',
            27 => 'Língua Indígena',
            23 => 'Libras',
            31 => 'Língua Portuguesa como Segunda Língua',
            10 => 'Arte',
            11 => 'Educação Física',
            // Matemática
            3  => 'Matemática',
            // Ciências da Natureza
            1  => 'Química',
            2  => 'Física',
            4  => 'Biologia',
            5  => 'Ciências',
            // Ciências Humanas e Sociais
            12 => 'História',
            13 => 'Geografia',
            14 => 'Filosofia',
            28 => 'Estudos Sociais',
            29 => 'Sociologia',
            // Outras áreas
            16 => 'Informática/Computação',
            17 => 'Áreas do conhecimento profissionalizantes',
            25 => 'Áreas do conhecimento pedagógicas',
            26 => 'Ensino Religioso',
            32 => 'Estágio Curricular Supervisionado',
            33 => 'Projeto de Vida',
            99 => 'Outras áreas do conhecimento',
        ];

        foreach ($componentes as $cod => $nome) {
            DB::table('cfg_censo_disciplina_depara')->updateOrInsert(
                ['cdd_codigo' => $cod],
                ['cdd_nome' => $nome, 'cdd_updated_at' => $now, 'cdd_created_at' => $now],
            );
        }
    }
}
