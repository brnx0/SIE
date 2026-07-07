<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia de-para de componente curricular Educacenso → disciplina do SIE.
 * cdd_dis_id fica nulo — o admin mapeia por base (disciplina exige área de
 * conhecimento). Nomes conforme dicionário INEP. Idempotente (upsert por codigo).
 */
class CensoDisciplinaDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $componentes = [
            1  => 'Língua/Literatura Portuguesa',
            2  => 'Língua/Literatura Estrangeira - Inglês',
            3  => 'Língua/Literatura Estrangeira - Espanhol',
            4  => 'Língua/Literatura Estrangeira - Outra',
            5  => 'Educação Física',
            6  => 'Arte',
            7  => 'História',
            8  => 'Geografia',
            9  => 'Filosofia',
            10 => 'Matemática',
            11 => 'Física',
            12 => 'Química',
            13 => 'Biologia',
            14 => 'Ciências',
            15 => 'Ensino Religioso',
            16 => 'Língua Indígena',
            17 => 'Estudos Sociais',
            18 => 'Sociologia',
            19 => 'Informática/Computação',
            20 => 'Estágio Curricular Supervisionado',
            21 => 'Ética e Cidadania',
            22 => 'Disciplinas Profissionalizantes',
            23 => 'Libras',
            24 => 'Projeto de Vida',
            25 => 'Outras disciplinas',
        ];

        foreach ($componentes as $cod => $nome) {
            DB::table('cfg_censo_disciplina_depara')->updateOrInsert(
                ['cdd_codigo' => $cod],
                ['cdd_nome' => $nome, 'cdd_updated_at' => $now, 'cdd_created_at' => $now],
            );
        }
    }
}
