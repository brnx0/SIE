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
use Illuminate\Support\Str;
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

        $turmaSegNome = (string) ($turma->segmento?->seg_nome_reduzido ?? '');
        $turSerId     = (int) $turma->tur_ser_id;

        // Aulas da turma no mês (não migradas) + ausências por aluno. Carrega a flag de
        // "não executada" para descartar essas aulas das contagens.
        $todasAulas = DB::table('edu_diario_aula')
            ->where('aul_tur_id', $turma->tur_id)
            ->where('aul_fl_migrada', false)
            ->whereNull('aul_deleted_at')
            ->whereYear('aul_dt', $ano->anl_ano)
            ->whereMonth('aul_dt', $mes)
            ->get(['aul_id', 'aul_dt', 'aul_fl_nao_executada']);

        // Aulas efetivas = executadas. Aulas não executadas não contam falta nem presença.
        $aulas  = $todasAulas->where('aul_fl_nao_executada', false)->values();
        $aulIds = $aulas->pluck('aul_id')->all();

        // [aln_id][aul_id] = true (ausências)
        $absent = [];
        foreach (DB::table('edu_diario_falta')->whereIn('fal_aul_id', $aulIds ?: [0])->where('fal_fl_presente', false)->whereNull('fal_deleted_at')->get(['fal_aln_id', 'fal_aul_id']) as $f) {
            $absent[(int) $f->fal_aln_id][(int) $f->fal_aul_id] = true;
        }

        // Aulas agrupadas por dia (p/ o modo "dias letivos") — só as executadas.
        $aulasPorDia = $aulas->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))
            ->map(fn ($g) => $g->pluck('aul_id')->map(fn ($v) => (int) $v)->all());

        $totalAulas  = count($aulIds);

        // Dias totalmente não executados (todas as aulas do dia marcadas) → descontam da base de dias.
        $diasTodosNaoExec = $todasAulas
            ->groupBy(fn ($a) => substr((string) $a->aul_dt, 0, 10))
            ->filter(fn ($g) => $g->every(fn ($a) => (bool) $a->aul_fl_nao_executada))
            ->count();

        // Dias letivos da rede (parâmetro). Sempre carregado: numa multi-seriada pode haver
        // alunos de F1/Pré (contam por dia) junto de F2/EJA (contam por aula) na mesma turma.
        $diasLetivosParam = $this->diasLetivosMes((int) $ano->anl_id, $mes);
        $diasLetivos = $diasLetivosParam !== null ? max(0, $diasLetivosParam - $diasTodosNaoExec) : null;

        // Situação na turma (edu_turma_aluno_situacao): descrição de enturmação.
        $situacoes = DB::table('edu_turma_aluno_situacao')
            ->get(['tas_cod', 'tas_descricao', 'tas_descricao_enturmacao'])
            ->mapWithKeys(fn ($s) => [(int) $s->tas_cod => $s->tas_descricao_enturmacao ?: $s->tas_descricao])
            ->all();

        $matriculas = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', '!=', Matricula::SITUACAO_CANCELADA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values();

        // Modo é decidido pelo segmento de CADA aluno (não da turma): a multi-seriada pode ter
        // alunos de qualquer segmento. Série do aluno = tma_ser_id (própria) ?? série da turma.
        $serIds = $matriculas->map(fn ($m) => (int) ($m->tma_ser_id ?? $turSerId))->unique()->values()->all();
        $segPorSerie = DB::table('edu_serie as s')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereIn('s.ser_id', $serIds ?: [0])
            ->pluck('seg.seg_nome_reduzido', 's.ser_id')
            ->all();

        $modosUsados = [];
        $alunos = $matriculas->map(function (Matricula $m) use ($absent, $aulasPorDia, $totalAulas, $diasLetivos, $situacoes, $segPorSerie, $turSerId, $turmaSegNome, &$modosUsados) {
            $alnId  = (int) $m->aluno->aln_id;
            $aus    = $absent[$alnId] ?? [];
            $serId  = (int) ($m->tma_ser_id ?? $turSerId);
            $segAln = (string) ($segPorSerie[$serId] ?? $turmaSegNome);
            $modo   = $this->modoPorSegmento($segAln);
            $modosUsados[$modo] = true;

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

            // Situação de enturmação = código de entrada do aluno na turma.
            $codSit = $m->tma_tas_cod_entrada;

            return [
                'aln_id'           => $alnId,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'segmento'         => $segAln,
                'modo'             => $modo,
                'base'             => $base,
                'faltas'           => $faltas,
                'frequencia'       => $freq,
                'situacao'         => $codSit !== null ? ($situacoes[(int) $codSit] ?? '—') : '—',
            ];
        });

        // Turma "mista": multi-seriada explícita OU alunos com modos diferentes → mostra coluna de segmento.
        $multi = str_contains(mb_strtoupper(Str::ascii($turmaSegNome), 'UTF-8'), 'MULTI') || count($modosUsados) > 1;

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/FrequenciaMensal/Resultado', [
            'parametros' => $p ? ['nome_entidade' => $p->par_nome_entidade, 'logomarca_url' => $p->par_logomarca_url, 'brasao_url' => $p->par_brasao_url] : null,
            'filtros'    => [
                'anl_ano'   => $ano->anl_ano,
                'esc_nome'  => $escola->esc_nome,
                'turma'     => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'segmento'  => $turmaSegNome,
                'mes_label' => (self::MESES[$mes] ?? '') . '/' . $ano->anl_ano,
            ],
            'multi'        => $multi,
            'dias_letivos' => $diasLetivos,
            'total_aulas'  => $totalAulas,
            'alunos'       => $alunos,
        ]);
    }

    /**
     * Pré-escola e Fundamental I → contabilizam por DIAS LETIVOS (parâmetro).
     * Fundamental II, EJA e Creche → por AULAS da turma.
     * (Multi-seriada não tem modo próprio: resolvido por aluno, via o segmento da série dele.)
     */
    private function modoPorSegmento(string $segNome): string
    {
        // Str::ascii remove acentos ("Pré" → "Pre") p/ casar o match sem depender de acentuação.
        $n = mb_strtoupper(Str::ascii($segNome), 'UTF-8');

        // Fundamental II / EJA / Creche → aulas (testa II antes de "FUNDAMENTAL" genérico).
        if (str_contains($n, 'FUNDAMENTAL II') || str_contains($n, 'EJA') || str_contains($n, 'CRECHE')) {
            return 'aulas';
        }
        // Fundamental I e Pré-escola → dias letivos.
        if (str_contains($n, 'FUNDAMENTAL') || str_contains($n, 'PRE')) {
            return 'dias';
        }

        return 'aulas'; // padrão
    }

    /** Dias letivos cadastrados (parâmetro) p/ o ano no mês — único para toda a rede. */
    private function diasLetivosMes(int $anlId, int $mes): ?int
    {
        $raw = DB::table('cfg_dias_letivos')
            ->where('dlt_anl_id', $anlId)
            ->value('dlt_meses');

        if (! $raw) {
            return null;
        }
        $meses = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return isset($meses[$mes]) ? (int) $meses[$mes] : (isset($meses[(string) $mes]) ? (int) $meses[(string) $mes] : null);
    }
}
