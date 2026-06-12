<?php

namespace App\Http\Controllers\Serie;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serie\StoreSerieRequest;
use App\Models\Disciplina\Disciplina;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\GradeDisciplinar;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use App\Models\Serie\SerieIndicador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SerieController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $series = $this->baseQuery($request)
            ->with('segmento:seg_id,seg_nome_reduzido')
            ->orderBy('seg_id')
            ->orderBy('ser_ordem_no_segmento')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('series/Index', [
            'series'    => $series,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $series = $this->baseQuery($request)
            ->with('segmento:seg_id,seg_nome_reduzido')
            ->orderBy('seg_id')
            ->orderBy('ser_ordem_no_segmento')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($series, $request);
        }

        return $this->exportCsv($series);
    }

    public function create(): Response
    {
        return Inertia::render('series/Create', [
            'segmentos' => Segmento::orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
        ]);
    }

    public function store(StoreSerieRequest $request): RedirectResponse
    {
        $serie = Serie::create($request->validated());

        return to_route('series.edit', $serie)->with('success', 'Série cadastrada com sucesso.');
    }

    public function edit(Serie $serie, Request $request): Response
    {
        $serie->load([
            'promoSerie1:ser_id,ser_nome',
            'promoSerie2:ser_id,ser_nome',
            'consSerie1:ser_id,ser_nome',
            'consSerie2:ser_id,ser_nome',
        ]);

        $resumo = fn ($s) => $s ? ['ser_id' => $s->ser_id, 'ser_nome' => $s->ser_nome] : null;

        // Ano letivo selecionado (?anl_id) ou em exercício como padrão.
        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']);
        $anlIdSelecionado = (int) $request->input('anl_id');
        if (! $anlIdSelecionado || ! $anosLetivos->firstWhere('anl_id', $anlIdSelecionado)) {
            $anlIdSelecionado = (int) ($anosLetivos->firstWhere('anl_fl_em_exercicio', true)?->anl_id
                ?? $anosLetivos->first()?->anl_id);
        }

        // Disciplinas disponíveis = somente as que estão na grade do (ano, série) e ativas.
        $disciplinasGrade = Disciplina::query()
            ->whereExists(function ($q) use ($serie, $anlIdSelecionado) {
                $q->select(DB::raw(1))
                    ->from('edu_grade_disciplinar')
                    ->whereColumn('edu_grade_disciplinar.grd_dis_id', 'edu_disciplina.dis_id')
                    ->where('edu_grade_disciplinar.grd_ser_id', $serie->ser_id)
                    ->where('edu_grade_disciplinar.grd_anl_id', $anlIdSelecionado)
                    ->where('edu_grade_disciplinar.grd_fl_ativo', true)
                    ->whereNull('edu_grade_disciplinar.grd_deleted_at');
            })
            ->where('dis_fl_ativo', true)
            ->orderBy('dis_nome')
            ->get(['dis_id', 'dis_nome']);

        $indicadores = $serie->indicadores()
            ->with('disciplina:dis_id,dis_nome')
            ->where('ind_anl_id', $anlIdSelecionado)
            ->orderBy('ind_id')
            ->get(['ind_id', 'ind_ser_id', 'ind_dis_id', 'ind_anl_id', 'ind_descricao', 'ind_fl_ativo'])
            ->map(fn ($i) => [
                'ind_id'        => $i->ind_id,
                'ind_ser_id'    => $i->ind_ser_id,
                'ind_dis_id'    => $i->ind_dis_id,
                'ind_anl_id'    => $i->ind_anl_id,
                'ind_descricao' => $i->ind_descricao,
                'ind_fl_ativo'  => $i->ind_fl_ativo,
                'disciplina'    => $i->disciplina ? ['dis_id' => $i->disciplina->dis_id, 'dis_nome' => $i->disciplina->dis_nome] : null,
            ]);

        // Anos letivos com indicadores cadastrados nesta série (para replicar de outro ano).
        $anosComIndicadores = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->whereNotNull('ind_anl_id')
            ->where('ind_anl_id', '!=', $anlIdSelecionado)
            ->distinct()
            ->pluck('ind_anl_id')
            ->all();
        $anosReplicacao = $anosLetivos
            ->whereIn('anl_id', $anosComIndicadores)
            ->values()
            ->map(fn ($a) => ['anl_id' => $a->anl_id, 'anl_ano' => $a->anl_ano]);

        $seriesParaReplicar = Serie::query()
            ->where('ser_id', '!=', $serie->ser_id)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('edu_serie_indicador')
                    ->whereColumn('edu_serie_indicador.ind_ser_id', 'edu_serie.ser_id')
                    ->whereNull('edu_serie_indicador.ind_deleted_at');
            })
            ->orderBy('seg_id')
            ->orderBy('ser_ordem_no_segmento')
            ->with('segmento:seg_id,seg_nome_reduzido')
            ->get(['ser_id', 'ser_nome', 'seg_id'])
            ->map(fn ($s) => [
                'ser_id'   => $s->ser_id,
                'ser_nome' => $s->ser_nome,
                'seg_nome' => $s->segmento?->seg_nome_reduzido,
            ]);

        return Inertia::render('series/Edit', [
            'serie'     => array_merge($serie->toArray(), [
                'promoSerie1' => $resumo($serie->promoSerie1),
                'promoSerie2' => $resumo($serie->promoSerie2),
                'consSerie1'  => $resumo($serie->consSerie1),
                'consSerie2'  => $resumo($serie->consSerie2),
            ]),
            'segmentos'          => Segmento::orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
            'disciplinas'        => $disciplinasGrade,
            'indicadores'        => $indicadores,
            'seriesParaReplicar' => $seriesParaReplicar,
            'anosLetivos'        => $anosLetivos->map(fn ($a) => ['anl_id' => $a->anl_id, 'anl_ano' => $a->anl_ano, 'anl_fl_em_exercicio' => $a->anl_fl_em_exercicio]),
            'anlIdSelecionado'   => $anlIdSelecionado,
            'anosReplicacao'     => $anosReplicacao,
        ]);
    }

    public function update(StoreSerieRequest $request, Serie $serie): RedirectResponse
    {
        $serie->update($request->validated());

        return to_route('series.edit', $serie)->with('success', 'Série atualizada com sucesso.');
    }

    public function destroy(Serie $serie): RedirectResponse
    {
        return $this->safeDelete($serie)
            ?? to_route('series.index')->with('success', 'Série removida com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Serie::query()
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->whereRaw('ser_nome ilike ?', ["%{$s}%"])
                      ->orWhereRaw('ser_cd_referencia ilike ?', ["%{$s}%"]);
                });
            });
    }

    private function exportCsv($series): StreamedResponse
    {
        $filename = 'series_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($series) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'Segmento', 'Cód. Referência', 'Carga Horária (h)', 'Idade', 'Ordem', 'Situação'], ';');
            foreach ($series as $s) {
                fputcsv($out, [
                    $s->ser_nome,
                    $s->segmento?->seg_nome_reduzido ?? '',
                    $s->ser_cd_referencia ?? '',
                    $s->ser_carga_horaria ?? '',
                    $s->ser_idade,
                    $s->ser_ordem_no_segmento,
                    $s->ser_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($series, Request $request): HttpResponse
    {
        $collection = collect($series);
        $filename   = 'series_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.series_pdf', [
            'series'        => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('ser_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('ser_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
