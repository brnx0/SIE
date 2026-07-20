<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreMonitorRequest;
use App\Models\Transporte\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitorController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $monitores = $this->baseQuery($request)
            ->with('funcionario:fun_id,fun_nome')
            ->orderBy('tmo_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/monitores/Index', [
            'monitores' => $monitores,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $monitores = $this->baseQuery($request)
            ->with('funcionario:fun_id,fun_nome')
            ->orderBy('tmo_nome')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($monitores, $request);
        }

        return $this->exportCsv($monitores);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/monitores/Create');
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $monitor = Monitor::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('transporte.monitores.create')->with('success', 'Monitor cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('transporte.monitores.edit', $monitor)->with('success', 'Monitor cadastrado com sucesso.');
    }

    public function edit(Monitor $monitor): Response
    {
        $monitor->load('funcionario:fun_id,fun_nome,fun_cpf');

        return Inertia::render('transporte/monitores/Edit', [
            'monitor' => $monitor,
        ]);
    }

    public function update(StoreMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($request->validated());

        return to_route('transporte.monitores.edit', $monitor)->with('success', 'Monitor atualizado com sucesso.');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        return $this->safeDelete($monitor)
            ?? to_route('transporte.monitores.index')->with('success', 'Monitor removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Monitor::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s));
    }

    private function exportCsv($monitores): StreamedResponse
    {
        $filename = 'transporte_monitores_' . now()->format('Ymd_His') . '.csv';
        $fmt      = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return response()->streamDownload(function () use ($monitores, $fmt) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'CPF', 'Nascimento', 'Telefone', 'Vínculo Funcional', 'Situação'], ';');
            foreach ($monitores as $m) {
                fputcsv($out, [
                    $m->tmo_nome,
                    $m->tmo_cpf ?? '',
                    $fmt($m->tmo_dt_nascimento),
                    $m->tmo_telefone ?? '',
                    $m->funcionario?->fun_nome ?? '',
                    $m->tmo_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($monitores, Request $request): HttpResponse
    {
        $collection = collect($monitores);
        $filename   = 'transporte_monitores_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_monitores_pdf', [
            'monitores'     => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('tmo_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('tmo_fl_ativo', false)->count(),
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
