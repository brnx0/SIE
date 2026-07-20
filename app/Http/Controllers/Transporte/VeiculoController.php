<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreVeiculoRequest;
use App\Models\Transporte\Veiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VeiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $veiculos = $this->baseQuery($request)
            ->orderBy('vei_placa')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/veiculos/Index', [
            'veiculos' => $veiculos,
            'tipos'    => Veiculo::TIPOS,
            'filters'  => [
                'search'   => $request->input('search', ''),
                'tipo'     => $request->input('tipo', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $veiculos = $this->baseQuery($request)->orderBy('vei_placa')->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($veiculos, $request);
        }

        return $this->exportCsv($veiculos);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/veiculos/Create', [
            'tipos' => Veiculo::TIPOS,
        ]);
    }

    public function store(StoreVeiculoRequest $request): RedirectResponse
    {
        $veiculo = Veiculo::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('transporte.veiculos.create')->with('success', 'Veículo cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('transporte.veiculos.edit', $veiculo)->with('success', 'Veículo cadastrado com sucesso.');
    }

    public function edit(Veiculo $veiculo): Response
    {
        return Inertia::render('transporte/veiculos/Edit', [
            'veiculo' => $veiculo,
            'tipos'   => Veiculo::TIPOS,
        ]);
    }

    public function update(StoreVeiculoRequest $request, Veiculo $veiculo): RedirectResponse
    {
        $veiculo->update($request->validated());

        return to_route('transporte.veiculos.edit', $veiculo)->with('success', 'Veículo atualizado com sucesso.');
    }

    public function destroy(Veiculo $veiculo): RedirectResponse
    {
        return $this->safeDelete($veiculo)
            ?? to_route('transporte.veiculos.index')->with('success', 'Veículo removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Veiculo::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('tipo'), fn ($q, $t) => $q->where('vei_tipo', $t));
    }

    private function exportCsv($veiculos): StreamedResponse
    {
        $filename = 'transporte_veiculos_' . now()->format('Ymd_His') . '.csv';
        $fmt      = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return response()->streamDownload(function () use ($veiculos, $fmt) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Placa', 'Tipo', 'Capacidade', 'Marca', 'Modelo', 'Ano', 'RENAVAM', 'Acessível', 'Terceirizado', 'Licenciamento', 'Seguro', 'Vistoria', 'Situação'], ';');
            foreach ($veiculos as $v) {
                fputcsv($out, [
                    $v->vei_placa,
                    Veiculo::TIPOS[$v->vei_tipo] ?? $v->vei_tipo,
                    $v->vei_capacidade,
                    $v->vei_marca ?? '',
                    $v->vei_modelo ?? '',
                    $v->vei_ano_fabricacao ?? '',
                    $v->vei_renavam ?? '',
                    $v->vei_fl_acessivel ? 'Sim' : 'Não',
                    $v->vei_fl_terceirizado ? 'Sim' : 'Não',
                    $fmt($v->vei_dt_licenciamento),
                    $fmt($v->vei_dt_seguro),
                    $fmt($v->vei_dt_vistoria),
                    $v->vei_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($veiculos, Request $request): HttpResponse
    {
        $collection = collect($veiculos);
        $filename   = 'transporte_veiculos_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_veiculos_pdf', [
            'veiculos'      => $collection,
            'tipos'         => Veiculo::TIPOS,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('vei_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('vei_fl_ativo', false)->count(),
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
