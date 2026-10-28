<?php

namespace App\Support\Diario;

use App\Models\Matricula\Matricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Percentual de frequência do aluno num MÊS.
 *
 * Regra movida LITERALMENTE de App\Http\Controllers\Relatorio\FrequenciaMensalController::gerar
 * para que o relatório web e a API v1 do app usem o mesmo cálculo — antes disso
 * a fórmula existia em duas cópias (aqui e, para o ano, em
 * CalculoEncerramento::frequenciaAnual, que segue privado e intocado).
 *
 * Particularidades que NÃO são acidentes:
 *  · o modo de contagem é POR ALUNO, decidido pelo segmento da SÉRIE DELE — numa
 *    multi-seriada convivem alunos contados por dia e por aula na mesma turma;
 *  · modo 'dias' é tudo-ou-nada: só vira falta o dia em que o aluno faltou a
 *    TODAS as aulas executadas daquele dia;
 *  · aula NÃO EXECUTADA não conta falta nem presença, e um dia inteiro não
 *    executado é descontado da base de dias letivos;
 *  · `aul_fl_migrada = false` exclui as aulas espelho de remanejamento;
 *  · sem dias letivos cadastrados a base fica nula e a frequência sai `null`
 *    (célula vazia) — diferente do cálculo ANUAL, que nesse caso devolve 100;
 *  · falta justificada com motivo que ABONA não conta no percentual; sem abono,
 *    só é informada (ver JustificativasFalta — mesma regra do anual).
 */
class FrequenciaMensal
{
    /**
     * @return array{total_aulas:int, dias_letivos:?int, modos:array<string>, alunos:array<int,array>}
     */
    public static function calcular(int $turId, int $anlId, int $anlAno, int $mes): array
    {
        $turma = DB::table('edu_turma as t')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 't.tur_seg_id')
            ->where('t.tur_id', $turId)
            ->first(['t.tur_ser_id', 'seg.seg_nome_reduzido']);

        $turSerId = (int) ($turma->tur_ser_id ?? 0);
        $turmaSegNome = (string) ($turma->seg_nome_reduzido ?? '');

        $todasAulas = DB::table('edu_diario_aula')
            ->where('aul_tur_id', $turId)
            ->where('aul_fl_migrada', false)
            ->whereNull('aul_deleted_at')
            ->whereYear('aul_dt', $anlAno)
            ->whereMonth('aul_dt', $mes)
            ->get(['aul_id', 'aul_dt', 'aul_fl_nao_executada']);

        // Aulas efetivas = executadas. Não executadas não contam falta nem presença.
        $aulas = $todasAulas->where('aul_fl_nao_executada', false)->values();
        $aulIds = $aulas->pluck('aul_id')->all();

        // [aln_id][aul_id] = true (ausências)
        $absent = [];
        foreach (
            DB::table('edu_diario_falta')
                ->whereIn('fal_aul_id', $aulIds ?: [0])
                ->where('fal_fl_presente', false)
                ->whereNull('fal_deleted_at')
                ->get(['fal_aln_id', 'fal_aul_id']) as $f
        ) {
            $absent[(int) $f->fal_aln_id][(int) $f->fal_aul_id] = true;
        }

        $aulasPorDia = $aulas->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))
            ->map(fn ($g) => $g->pluck('aul_id')->map(fn ($v) => (int) $v)->all());

        $totalAulas = count($aulIds);

        // Dias totalmente não executados descontam da base de dias.
        $diasTodosNaoExec = $todasAulas
            ->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))
            ->filter(fn ($g) => $g->every(fn ($a) => (bool) $a->aul_fl_nao_executada))
            ->count();

        $diasLetivosParam = self::diasLetivosMes($anlId, $mes);
        $diasLetivos = $diasLetivosParam !== null ? max(0, $diasLetivosParam - $diasTodosNaoExec) : null;

        $situacoes = DB::table('edu_turma_aluno_situacao')
            ->get(['tas_cod', 'tas_descricao', 'tas_descricao_enturmacao'])
            ->mapWithKeys(fn ($s) => [(int) $s->tas_cod => $s->tas_descricao_enturmacao ?: $s->tas_descricao])
            ->all();

        $matriculas = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', '!=', Matricula::SITUACAO_CANCELADA)
            ->semRemanejado()
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values();

        // O modo é do segmento de CADA aluno; série do aluno = tma_ser_id ?? série da turma.
        $serIds = $matriculas->map(fn ($m) => (int) ($m->tma_ser_id ?? $turSerId))->unique()->values()->all();
        $segPorSerie = DB::table('edu_serie as s')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereIn('s.ser_id', $serIds ?: [0])
            ->pluck('seg.seg_nome_reduzido', 's.ser_id')
            ->all();

        // Dias justificados no mês, por aluno.
        $iniMes = sprintf('%04d-%02d-01', $anlAno, $mes);
        $justificados = JustificativasFalta::porDia(
            $matriculas->map(fn ($m) => (int) $m->aluno->aln_id)->all(),
            $iniMes,
            date('Y-m-t', strtotime($iniMes)),
        );

        $modosUsados = [];
        $alunos = $matriculas->map(function (Matricula $m) use (
            $absent, $aulasPorDia, $totalAulas, $diasLetivos, $situacoes, $segPorSerie, $turSerId, $turmaSegNome, $justificados, &$modosUsados
        ) {
            $alnId = (int) $m->aluno->aln_id;
            $aus = $absent[$alnId] ?? [];
            $serId = (int) ($m->tma_ser_id ?? $turSerId);
            $segAln = (string) ($segPorSerie[$serId] ?? $turmaSegNome);
            $modo = self::modoPorSegmento($segAln);
            $modosUsados[$modo] = true;

            // dias: falta = dia em que faltou a TODAS as aulas executadas; aulas: cada aula.
            $c = JustificativasFalta::contar($modo, $aus, $aulasPorDia, $justificados[$alnId] ?? []);
            $base = $modo === 'dias' ? $diasLetivos : $totalAulas;
            $faltas = $c['faltas'];

            // Falta abonada (motivo que abona) não conta no percentual.
            $freq = ($base && $base > 0) ? round(max(0, $base - ($faltas - $c['abonadas'])) / $base * 100, 1) : null;
            $codSit = $m->tma_tas_cod_entrada;

            return [
                'aln_id'           => $alnId,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'segmento'         => $segAln,
                'modo'             => $modo,
                'base'             => $base,
                'faltas'           => $faltas,
                'faltas_justificadas' => $c['justificadas'],
                'faltas_abonadas'  => $c['abonadas'],
                'frequencia'       => $freq,
                'situacao'         => $codSit !== null ? ($situacoes[(int) $codSit] ?? '—') : '—',
            ];
        });

        return [
            'total_aulas'  => $totalAulas,
            'dias_letivos' => $diasLetivos,
            'modos'        => array_keys($modosUsados),
            'alunos'       => $alunos->all(),
        ];
    }

    /** Fundamental II / EJA / Creche contam por AULA; Fundamental I e Pré, por DIA. */
    public static function modoPorSegmento(string $segNome): string
    {
        // Str::ascii remove acentos ("Pré" → "Pre") p/ casar sem depender de acentuação.
        $n = mb_strtoupper(Str::ascii($segNome), 'UTF-8');

        // Testa "II" antes do "FUNDAMENTAL" genérico.
        if (str_contains($n, 'FUNDAMENTAL II') || str_contains($n, 'EJA') || str_contains($n, 'CRECHE')) {
            return 'aulas';
        }
        if (str_contains($n, 'FUNDAMENTAL') || str_contains($n, 'PRE')) {
            return 'dias';
        }

        return 'aulas';
    }

    public static function diasLetivosMes(int $anlId, int $mes): ?int
    {
        $raw = DB::table('cfg_dias_letivos')->where('dlt_anl_id', $anlId)->value('dlt_meses');

        if (! $raw) {
            return null;
        }
        $meses = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return isset($meses[$mes])
            ? (int) $meses[$mes]
            : (isset($meses[(string) $mes]) ? (int) $meses[(string) $mes] : null);
    }
}
