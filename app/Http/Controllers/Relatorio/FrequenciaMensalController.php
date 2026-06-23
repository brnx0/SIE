<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FrequenciaMensalController extends Controller
{
    use NotaLookups;

    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FrequenciaMensal/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'meses'       => collect(self::MESES)->map(fn ($nome, $num) => ['id' => $num, 'label' => $nome])->values(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'mes'    => ['required', 'integer', Rule::in(range(1, 12))],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome', 'segmento:seg_id,seg_nome_reduzido')->findOrFail($data['tur_id']);
        $mes    = (int) $data['mes'];

        $segNome = (string) ($turma->segmento?->seg_nome_reduzido ?? '');
        $modo    = $this->modoPorSegmento($segNome); // 'dias' | 'aulas'

        // Aulas da turma no mês (não migradas) + ausências por aluno.
        $aulas = DB::table('edu_diario_aula')
            ->where('aul_tur_id', $turma->tur_id)
            ->where('aul_fl_migrada', false)
            ->whereNull('aul_deleted_at')
            ->whereYear('aul_dt', $ano->anl_ano)
            ->whereMonth('aul_dt', $mes)
            ->get(['aul_id', 'aul_dt']);

        $aulIds = $aulas->pluck('aul_id')->all();

        // [aln_id][aul_id] = true (ausências)
        $absent = [];
        foreach (DB::table('edu_diario_falta')->whereIn('fal_aul_id', $aulIds ?: [0])->where('fal_fl_presente', false)->whereNull('fal_deleted_at')->get(['fal_aln_id', 'fal_aul_id']) as $f) {
            $absent[(int) $f->fal_aln_id][(int) $f->fal_aul_id] = true;
        }

        // Aulas agrupadas por dia (p/ o modo "dias letivos").
        $aulasPorDia = $aulas->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))
            ->map(fn ($g) => $g->pluck('aul_id')->map(fn ($v) => (int) $v)->all());

        $totalAulas   = count($aulIds);
        $diasLetivos  = $modo === 'dias' ? $this->diasLetivosMes((int) $ano->anl_id, (int) $turma->tur_seg_id, $mes) : null;

        // Situação na turma (edu_turma_aluno_situacao): descrição de enturmação.
        $situacoes = DB::table('edu_turma_aluno_situacao')
            ->get(['tas_cod', 'tas_descricao', 'tas_descricao_enturmacao'])
            ->mapWithKeys(fn ($s) => [(int) $s->tas_cod => $s->tas_descricao_enturmacao ?: $s->tas_descricao])
            ->all();

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', '!=', Matricula::SITUACAO_CANCELADA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function (Matricula $m) use ($modo, $absent, $aulasPorDia, $totalAulas, $diasLetivos, $situacoes) {
                $alnId = (int) $m->aluno->aln_id;
                $aus   = $absent[$alnId] ?? [];

                if ($modo === 'dias') {
                    // Falta = dia em que faltou TODAS as disciplinas com aula.
                    $diasFalta = 0;
                    foreach ($aulasPorDia as $auls) {
                        if (! empty($auls) && count(array_intersect($auls, array_keys($aus))) === count($auls)) {
                            $diasFalta++;
                        }
                    }
                    $base   = $diasLetivos;
                    $faltas = $diasFalta;
                } else {
                    $base   = $totalAulas;
                    $faltas = count($aus);
                }

                $freq = ($base && $base > 0) ? round(max(0, $base - $faltas) / $base * 100, 1) : null;

                // Saída tem precedência (transferido/remanejado/evadido...); senão a entrada (matriculado).
                $codSit = $m->tma_tas_cod_saida ?? $m->tma_tas_cod_entrada;

                return [
                    'aln_id'           => $alnId,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'base'             => $base,
                    'faltas'           => $faltas,
                    'frequencia'       => $freq,
                    'situacao'         => $codSit !== null ? ($situacoes[(int) $codSit] ?? '—') : '—',
                ];
            });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/FrequenciaMensal/Resultado', [
            'parametros' => $p ? ['nome_entidade' => $p->par_nome_entidade, 'logomarca_url' => $p->par_logomarca_url, 'brasao_url' => $p->par_brasao_url] : null,
            'filtros'    => [
                'anl_ano'   => $ano->anl_ano,
                'esc_nome'  => $escola->esc_nome,
                'turma'     => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'segmento'  => $segNome,
                'mes_label' => (self::MESES[$mes] ?? '') . '/' . $ano->anl_ano,
            ],
            'modo'         => $modo,
            'dias_letivos' => $diasLetivos,
            'total_aulas'  => $totalAulas,
            'alunos'       => $alunos,
        ]);
    }

    /** F2 e EJA → contabiliza por DIAS LETIVOS; demais (creche/pré/fund1/multi) → por AULAS. */
    private function modoPorSegmento(string $segNome): string
    {
        $n = mb_strtoupper($segNome, 'UTF-8');

        return (str_contains($n, 'FUNDAMENTAL II') || str_contains($n, 'EJA')) ? 'dias' : 'aulas';
    }

    /** Dias letivos cadastrados (parâmetro) p/ o ano+segmento no mês. */
    private function diasLetivosMes(int $anlId, int $segId, int $mes): ?int
    {
        $raw = DB::table('cfg_dias_letivos')
            ->where('dlt_anl_id', $anlId)
            ->where('dlt_seg_id', $segId)
            ->value('dlt_meses');

        if (! $raw) {
            return null;
        }
        $meses = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return isset($meses[$mes]) ? (int) $meses[$mes] : (isset($meses[(string) $mes]) ? (int) $meses[(string) $mes] : null);
    }
}
