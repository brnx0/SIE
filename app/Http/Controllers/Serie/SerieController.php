<?php

namespace App\Http\Controllers\Serie;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serie\StoreSerieRequest;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Serie::create($request->validated());

        return to_route('series.index')->with('success', 'Série cadastrada com sucesso.');
    }

    public function edit(Serie $serie): Response
    {
        $serie->load([
            'promoSerie1:ser_id,ser_nome',
            'promoSerie2:ser_id,ser_nome',
            'consSerie1:ser_id,ser_nome',
            'consSerie2:ser_id,ser_nome',
        ]);

        return Inertia::render('series/Edit', [
            'serie'     => $serie,
            'segmentos' => Segmento::orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
        ]);
    }

    public function update(StoreSerieRequest $request, Serie $serie): RedirectResponse
    {
        $serie->update($request->validated());

        return to_route('series.index')->with('success', 'Série atualizada com sucesso.');
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
