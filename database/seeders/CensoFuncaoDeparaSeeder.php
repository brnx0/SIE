<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * De-para função/cargo Educacenso → cargo do SIE, com o mapeamento FIXADO por
 * nome de cargo (resolve o crg_id no seed). Idempotente (upsert por origem+codigo).
 * Ver docs/regras/importacao_censo.md.
 */
class CensoFuncaoDeparaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // origem docente (reg50 col7). [codigo, nome_censo, nome_cargo|null]
        $docente = [
            [1, 'Docente', 'Professor(a)'],
            [2, 'Auxiliar/Assistente Educacional', null],
            [3, 'Profissional/Monitor de Atividade Complementar', null],
            [4, 'Tradutor Intérprete de Libras', null],
            [5, 'Docente Titular - Coordenador de Tutoria (EAD)', null],
            [6, 'Docente Tutor - Auxiliar (EAD)', null],
            [7, 'Guia-Intérprete', null],
            [8, 'Profissional de Apoio Escolar', 'Professor(a) de Apoio'],
        ];

        // origem gestor (reg40 col5). [codigo, nome_censo, nome_cargo|null]
        $gestor = [
            [1, 'Diretor', 'Diretor(a)'],
            [2, 'Gestor Escolar', null],
        ];

        foreach ($docente as [$cod, $nome, $cargo]) {
            $this->upsert('docente', $cod, $nome, $cargo, $now);
        }
        foreach ($gestor as [$cod, $nome, $cargo]) {
            $this->upsert('gestor', $cod, $nome, $cargo, $now);
        }
    }

    private function upsert(string $origem, int $cod, string $nome, ?string $nomeCargo, $now): void
    {
        $crgId = $nomeCargo
            ? DB::table('edu_cargo')->where('crg_nome', $nomeCargo)->value('crg_id')
            : null;

        DB::table('cfg_censo_funcao_depara')->updateOrInsert(
            ['cfd_origem' => $origem, 'cfd_codigo' => $cod],
            [
                'cfd_cargo_nome' => $nome,
                'cfd_crg_id'     => $crgId,
                'cfd_updated_at' => $now,
                'cfd_created_at' => $now,
            ],
        );
    }
}
