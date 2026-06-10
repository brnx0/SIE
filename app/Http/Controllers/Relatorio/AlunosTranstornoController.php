<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlunosTranstornoController extends Controller
{
    private const TRANSTORNOS = [
        'tea'         => ['label' => 'TEA',         'conjunto' => 'globais', 'item' => 'Autismo Infantil - TEA'],
        'tdah'        => ['label' => 'TDAH',        'conjunto' => 'aprend',  'item' => 'TDAH (Transtorno do Déficit de Atenção com Hiperatividade)'],
        'dislexia'    => ['label' => 'Dislexia',    'conjunto' => 'aprend',  'item' => 'Dislexia'],
        'disgrafia'   => ['label' => 'Disgrafia',   'conjunto' => 'aprend',  'item' => 'Disgrafia, Disortografia ou outro transtorno da escrita e ortografia'],
        'discalculia' => ['label' => 'Discalculia', 'conjunto' => 'aprend',  'item' => 'Discalculia ou outro transtorno da matemática e raciocínio lógico'],
        'dislalia'    => ['label' => 'Dislalia',    'conjunto' => 'aprend',  'item' => 'Dislalia ou outro transtorno da linguagem e comunicação'],
        'tpac'        => ['label' => 'TPAC',        'conjunto' => 'aprend',  'item' => 'Transtorno do Processamento Auditivo Central (TPAC)'],
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlunosTranstorno/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'seg_id' => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
            'ser_id' => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
        ]);

        $anoLetivo    = AnoLetivo::findOrFail($data['anl_id']);
        $parametros   = ParametroEntidade::first();
        $escolaFiltro = ! empty($data['esc_id']) ? Escola::find($data['esc_id']) : null;

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula,aln_filiacao_1,aln_filiacao_2',
                'aluno.saude:als_id,als_aln_id,als_transtornos_globais,als_transtornos_aprendizagem',
                'turma:tur_id,tur_nome,tur_cd_inep,tur_esc_id,tur_anl_id,tur_modalidade,tur_seg_id,tur_ser_id',
                'turma.escola:esc_id,esc_nome',
                'turma.serie:ser_id,ser_nome',
                'turma.segmento:seg_id,seg_nome_reduzido',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->where('tma_anl_id', $data['anl_id'])
            ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->when(! empty($data['esc_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_esc_id', $data['esc_id'])))
            ->when(! empty($data['seg_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_seg_id', $data['seg_id'])))
            ->when(! empty($data['ser_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_ser_id', $data['ser_id'])))
            ->whereHas('aluno.saude', fn ($q) => $q
                ->whereRaw("jsonb_array_length(coalesce(als_transtornos_globais, '[]')::jsonb) > 0")
                ->orWhereRaw("jsonb_array_length(coalesce(als_transtornos_aprendizagem, '[]')::jsonb) > 0"))
            ->get();

        $linhas = $matriculas->map(function (Matricula $m) {
            $aln    = $m->aluno;
            $saude  = $aln?->saude;
            $turma  = $m->turma;

            $globais = (array) ($saude?->als_transtornos_globais ?? []);
            $aprend  = (array) ($saude?->als_transtornos_aprendizagem ?? []);

            $indicadores = [];
            foreach (self::TRANSTORNOS as $key => $cfg) {
                $lista = $cfg['conjunto'] === 'globais' ? $globais : $aprend;
                $indicadores[$key] = in_array($cfg['item'], $lista, true);
            }

            return [
                'inep_turma' => $turma?->tur_cd_inep,
                'escola'     => $turma?->escola?->esc_nome,
                'segmento'   => $turma?->segmento?->seg_nome_reduzido,
                'serie'      => $turma?->serie?->ser_nome,
                'turma'      => $turma?->tur_nome,
                'mat_aluno'  => $aln?->aln_nr_matricula,
                'aln_nome'   => $aln?->aln_nome,
                'filiacao'   => $aln?->aln_filiacao_1 ?: $aln?->aln_filiacao_2,
                'transt'     => $indicadores,
                'has_any'    => collect($indicadores)->contains(true),
            ];
        })
            ->filter(fn ($r) => $r['has_any'])
            ->sortBy([['escola', 'asc'], ['turma', 'asc'], ['aln_nome', 'asc']])
            ->values();

        $totais = ['total' => $linhas->count()];
        foreach (array_keys(self::TRANSTORNOS) as $k) {
            $totais[$k] = $linhas->where("transt.$k", true)->count();
        }

        return Inertia::render('relatorios/AlunosTranstorno/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'anoLetivo' => ['anl_id' => $anoLetivo->anl_id, 'anl_ano' => $anoLetivo->anl_ano],
            'escola'    => $escolaFiltro ? ['esc_id' => $escolaFiltro->esc_id, 'esc_nome' => $escolaFiltro->esc_nome] : null,
            'colunasTranstornos' => collect(self::TRANSTORNOS)
                ->map(fn ($cfg, $key) => ['key' => $key, 'label' => $cfg['label']])
                ->values(),
            'linhas' => $linhas,
            'totais' => $totais,
        ]);
    }
}
