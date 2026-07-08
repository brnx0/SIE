<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia de-para de função/cargo Educacenso → nome de cargo do SIE.
 * cfd_crg_id fica nulo; o commit faz find-or-create em edu_cargo pelo nome.
 * Idempotente (upsert por origem+codigo).
 */
class CensoFuncaoDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // origem docente (reg50 col7 — função exercida)
        $docente = [
            1 => 'Docente',
            2 => 'Auxiliar/Assistente Educacional',
            3 => 'Profissional/Monitor de Atividade Complementar',
            4 => 'Tradutor Intérprete de Libras',
            5 => 'Docente Titular - Coordenador de Tutoria (EAD)',
            6 => 'Docente Tutor - Auxiliar (EAD)',
            7 => 'Guia-Intérprete',
            8 => 'Profissional de Apoio Escolar',
        ];

        // origem gestor (reg40 col5 — cargo do gestor)
        $gestor = [
            1 => 'Diretor',
            2 => 'Gestor Escolar',
        ];

        foreach ($docente as $cod => $nome) {
            $this->upsert('docente', $cod, $nome, $now);
        }
        foreach ($gestor as $cod => $nome) {
            $this->upsert('gestor', $cod, $nome, $now);
        }
    }

    private function upsert(string $origem, int $cod, string $nome, $now): void
    {
        DB::table('cfg_censo_funcao_depara')->updateOrInsert(
            ['cfd_origem' => $origem, 'cfd_codigo' => $cod],
            ['cfd_cargo_nome' => $nome, 'cfd_updated_at' => $now, 'cfd_created_at' => $now],
        );
    }
}
