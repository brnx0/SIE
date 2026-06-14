<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\StoreInstrumentoAvaliativoRequest;
use App\Models\Diario\InstrumentoAvaliativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstrumentoAvaliativoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $instrumentos = $this->baseQuery($request)
            ->orderBy('iav_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('diario/instrumentos-avaliativos/Index', [
            'instrumentos' => $instrumentos,
            'filters'      => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $instrumentos = $this->baseQuery($request)
            ->orderBy('iav_nome')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($instrumentos, $request);
        }

        return $this->exportCsv($instrumentos);
    }

    public function create(): Response
    {
        return Inertia::render('diario/instrumentos-avaliativos/Create');
    }

    public function store(StoreInstrumentoAvaliativoRequest $request): RedirectResponse
    {
        $instrumento = InstrumentoAvaliativo::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('diario.instrumentos-avaliativos.create')
                ->with('success', 'Instrumento cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('diario.instrumentos-avaliativos.edit', $instrumento)
            ->with('success', 'Instrumento cadastrado com sucesso.');
    }

    public function edit(InstrumentoAvaliativo $instrumento): Response
    {
        return Inertia::render('diario/instrumentos-avaliativos/Edit', [
            'instrumento' => $instrumento,
        ]);
    }

    public function update(StoreInstrumentoAvaliativoRequest $request, InstrumentoAvaliativo $instrumento): RedirectResponse
    {
        $instrumento->update($request->validated());

        return to_route('diario.instrumentos-avaliativos.edit', $instrumento)
            ->with('success', 'Instrumento atualizado com sucesso.');
    }

    public function destroy(InstrumentoAvaliativo $instrumento): RedirectResponse
    {
        return $this->safeDelete($instrumento)
            ?? to_route('diario.instrumentos-avaliativos.index')
                ->with('success', 'Instrumento removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return InstrumentoAvaliativo::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s));
    }

    private function exportCsv($instrumentos): StreamedResponse
    {
        $filename = 'instrumentos_avaliativos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($instrumentos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'Situação'], ';');
            foreach ($instrumentos as $i) {
                fputcsv($out, [
                    $i->iav_nome,
                    $i->iav_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($instrumentos, Request $request): HttpResponse
    {
        $collection = collect($instrumentos);
        $filename   = 'instrumentos_avaliativos_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.instrumentos_avaliativos_pdf', [
            'instrumentos'  => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('iav_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('iav_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf([
            'orientation' => 'P', 'format' => 'A4',
            'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
