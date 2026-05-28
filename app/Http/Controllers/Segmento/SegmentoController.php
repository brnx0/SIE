<?php

namespace App\Http\Controllers\Segmento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Segmento\StoreSegmentoRequest;
use App\Models\Segmento\Segmento;
use Mpdf\Mpdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SegmentoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $segmentos = $this->baseQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('segmentos/Index', [
            'segmentos' => $segmentos,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $segmentos = $this->baseQuery($request)->orderBy('seg_ordem')->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($segmentos, $request);
        }

        return $this->exportCsv($segmentos);
    }

    public function create(): Response
    {
        return Inertia::render('segmentos/Create');
    }

    public function store(StoreSegmentoRequest $request): RedirectResponse
    {
        Segmento::create($request->validated());

        return to_route('segmentos.index')->with('success', 'Segmento cadastrado com sucesso.');
    }

    public function edit(Segmento $segmento): Response
    {
        return Inertia::render('segmentos/Edit', ['segmento' => $segmento]);
    }

    public function update(StoreSegmentoRequest $request, Segmento $segmento): RedirectResponse
    {
        $segmento->update($request->validated());

        return to_route('segmentos.index')->with('success', 'Segmento atualizado com sucesso.');
    }

    public function destroy(Segmento $segmento): RedirectResponse
    {
        return $this->safeDelete($segmento)
            ?? to_route('segmentos.index')->with('success', 'Segmento removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Segmento::query()
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->whereRaw('seg_nome_reduzido ilike ?', ["%{$s}%"])
                      ->orWhereRaw('seg_nome_completo ilike ?', ["%{$s}%"])
                      ->orWhereRaw('seg_cd_inep ilike ?', ["%{$s}%"]);
                });
            });
    }

    private function exportCsv(iterable $segmentos): StreamedResponse
    {
        $filename = 'segmentos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($segmentos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($out, ['Nome Reduzido', 'Nome Completo', 'Código INEP', 'Anos Escolares', 'Ordem', 'Situação'], ';');

            foreach ($segmentos as $seg) {
                fputcsv($out, [
                    $seg->seg_nome_reduzido,
                    $seg->seg_nome_completo,
                    $seg->seg_cd_inep ?? '',
                    $seg->seg_qt_anos_escolares,
                    $seg->seg_ordem,
                    $seg->seg_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportPdf(iterable $segmentos, Request $request): HttpResponse
    {
        $collection = collect($segmentos);
        $filename   = 'segmentos_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.segmentos_pdf', [
            'segmentos'     => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('seg_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('seg_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
