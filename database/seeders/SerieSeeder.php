<?php

namespace Database\Seeders;

use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Database\Seeder;

class SerieSeeder extends Seeder
{
    public function run(): void
    {
        // CSV "SEGMENTO" → seg_nome_reduzido no banco
        $segmentoMap = [
            'CRECHE'         => 'Creche',
            'PRÉ ESCOLA'     => 'Pré-escolar',
            'FUNDAMENTAL I'  => 'Ensino Fundamental I',
            'FUNDAMENTAL II' => 'Ensino Fundamental II',
            'EJA I'          => 'EJA I',
            'EJA II'         => 'EJA II',
        ];

        $tipoAvaliacaoMap = [
            'DESCRITIVA'  => 'descritiva',
            'DIAGNOSTICO' => 'diagnostico',
            'NÚMERICA'    => 'numerica',
            'NUMERICA'    => 'numerica',
            'CONCEITUAL'  => 'conceitual',
        ];

        // Granularidade da avaliação descritiva por segmento:
        // por_aluno (1 registro por período/aluno) — Creche, Pré-escola, Fund I.
        // por_disciplina (1 por disciplina) — Fund II, EJA.
        $descritivaPorSegmento = [
            'CRECHE'         => 'por_aluno',
            'PRÉ ESCOLA'     => 'por_aluno',
            'FUNDAMENTAL I'  => 'por_aluno',
            'FUNDAMENTAL II' => 'por_disciplina',
            'EJA I'          => 'por_disciplina',
            'EJA II'         => 'por_disciplina',
        ];

        // [SEGMENTO_CSV, SER_NOME, TIPO_AVALIACAO_CSV, IDADE, ORDEM]
        $linhas = [
            ['CRECHE',         'GRUPO 01',    'DESCRITIVA',  1,  1],
            ['CRECHE',         'GRUPO 02',    'DESCRITIVA',  2,  2],
            ['CRECHE',         'GRUPO 03',    'DESCRITIVA',  3,  3],
            ['CRECHE',         'BERCARIO',    'DESCRITIVA',  0,  4],
            ['CRECHE',         'MULTI',       'DESCRITIVA',  1,  5],
            ['PRÉ ESCOLA',     'GRUPO 04',    'DIAGNOSTICO', 4,  6],
            ['PRÉ ESCOLA',     'GRUPO 05',    'DIAGNOSTICO', 5,  7],
            ['PRÉ ESCOLA',     'EIMULTI',     'DIAGNOSTICO', 4,  8],
            ['FUNDAMENTAL I',  '1º ANO',      'CONCEITUAL',  6,  9],
            ['FUNDAMENTAL I',  '2º ANO',      'CONCEITUAL',  7, 10],
            ['FUNDAMENTAL I',  '3º ANO',      'CONCEITUAL',  8, 11],
            ['FUNDAMENTAL I',  '4º ANO',      'NÚMERICA',    9, 12],
            ['FUNDAMENTAL I',  '5º ANO',      'NÚMERICA',   10, 13],
            ['FUNDAMENTAL I',  'EFMULTI',     'NÚMERICA',    6, 14],
            ['FUNDAMENTAL II', '6º ANO',      'NÚMERICA',   11, 15],
            ['FUNDAMENTAL II', '7º ANO',      'NÚMERICA',   12, 16],
            ['FUNDAMENTAL II', '8º ANO',      'NÚMERICA',   13, 17],
            ['FUNDAMENTAL II', '9º ANO',      'NÚMERICA',   14, 18],
            ['EJA I',          'TAP I',       'NÚMERICA',   15, 19],
            ['EJA I',          'TAP II',      'NÚMERICA',   15, 20],
            ['EJA I',          'TAP III',     'NÚMERICA',   15, 21],
            ['EJA I',          'EJAMULTI I',  'NÚMERICA',   15, 22],
            ['EJA II',         'TAP IV',      'NÚMERICA',   15, 23],
            ['EJA II',         'TAP V',       'NÚMERICA',   15, 24],
            ['EJA II',         'EJAMULTI II', 'NÚMERICA',   15, 25],
        ];

        $segmentosPorNome = Segmento::whereIn('seg_nome_reduzido', array_values($segmentoMap))
            ->get()
            ->keyBy('seg_nome_reduzido');

        foreach ($linhas as [$segCsv, $serNome, $tipoCsv, $idade, $ordem]) {
            $segNome = $segmentoMap[$segCsv] ?? null;
            if (! $segNome || ! isset($segmentosPorNome[$segNome])) {
                $this->command?->warn("Segmento não encontrado para CSV '{$segCsv}' — pulando série {$serNome}.");
                continue;
            }
            $segId = $segmentosPorNome[$segNome]->seg_id;
            $tipo  = $tipoAvaliacaoMap[$tipoCsv] ?? null;

            // Todas as séries têm avaliação descritiva (além do tipo do CSV).
            $tipos = array_values(array_unique(array_filter([$tipo, 'descritiva'])));
            $tipoDescritiva = $descritivaPorSegmento[$segCsv] ?? 'por_disciplina';

            Serie::updateOrCreate(
                ['seg_id' => $segId, 'ser_nome' => $serNome],
                [
                    'ser_idade'                     => $idade,
                    'ser_nr_ordenacao'              => $ordem,
                    'ser_ordem_no_segmento'         => $ordem,
                    'ser_tipo_avaliacao'            => $tipos,
                    'ser_tipo_avaliacao_descritiva' => $tipoDescritiva,
                    'ser_fl_ativo'                  => true,
                    'ser_fl_multi'                  => str_contains($serNome, 'MULTI'),
                ],
            );
        }

        // Segunda passada: vínculos de conservação.
        // ser_cons_ser_id_1 = própria série.
        // ser_cons_ser_id_2 = série MULTI do mesmo segmento (nome contém "MULTI").
        $segIdsCsv = [];
        foreach ($segmentoMap as $nome) {
            if (isset($segmentosPorNome[$nome])) {
                $segIdsCsv[] = $segmentosPorNome[$nome]->seg_id;
            }
        }

        $multiSeries = Serie::whereIn('seg_id', $segIdsCsv)
            ->whereRaw('ser_nome ILIKE ?', ['%MULTI%'])
            ->get(['ser_id', 'seg_id', 'ser_nome']);

        $multiPorSegmento = $multiSeries
            ->groupBy('seg_id')
            ->map(fn ($grupo) => $grupo->first()->ser_id);

        $multiSerIds = $multiSeries->pluck('ser_id')->all();

        // Segmentos sem conservação (Creche, Pré-escolar): mantém ambos campos nulos.
        $segmentosSemConservacao = collect(['Creche', 'Pré-escolar'])
            ->map(fn ($n) => $segmentosPorNome[$n]->seg_id ?? null)
            ->filter()
            ->values()
            ->all();

        $series = Serie::whereIn('seg_id', $segIdsCsv)->get(['ser_id', 'seg_id']);
        foreach ($series as $s) {
            if (in_array($s->seg_id, $segmentosSemConservacao, true)) {
                $s->ser_cons_ser_id_1 = null;
                $s->ser_cons_ser_id_2 = null;
            } else {
                $s->ser_cons_ser_id_1 = $s->ser_id;
                // Série MULTI: cons_2 será turma de origem do aluno (lógica em runtime).
                $s->ser_cons_ser_id_2 = in_array($s->ser_id, $multiSerIds, true)
                    ? null
                    : ($multiPorSegmento[$s->seg_id] ?? null);
            }
            $s->save();
        }
    }
}
