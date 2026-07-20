<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreMotoristaRequest;
use App\Models\Transporte\Motorista;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MotoristaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $motoristas = $this->baseQuery($request)
            ->orderBy('mot_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/motoristas/Index', [
            'motoristas' => $motoristas,
            'filters'    => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $motoristas = $this->baseQuery($request)->orderBy('mot_nome')->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($motoristas, $request);
        }

        return $this->exportCsv($motoristas);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/motoristas/Create');
    }

    public function store(StoreMotoristaRequest $request): RedirectResponse
    {
        $motorista = Motorista::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('transporte.motoristas.create')->with('success', 'Motorista cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('transporte.motoristas.edit', $motorista)->with('success', 'Motorista cadastrado com sucesso.');
    }

    public function edit(Motorista $motorista): Response
    {
        $motorista->load('funcionario:fun_id,fun_nome,fun_cpf');

        return Inertia::render('transporte/motoristas/Edit', [
            'motorista' => $motorista,
        ]);
    }

    public function update(StoreMotoristaRequest $request, Motorista $motorista): RedirectResponse
    {
        $motorista->update($request->validated());

        return to_route('transporte.motoristas.edit', $motorista)->with('success', 'Motorista atualizado com sucesso.');
    }

    public function destroy(Motorista $motorista): RedirectResponse
    {
        return $this->safeDelete($motorista)
            ?? to_route('transporte.motoristas.index')->with('success', 'Motorista removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Motorista::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s));
    }

    private function exportCsv($motoristas): StreamedResponse
    {
        $filename = 'transporte_motoristas_' . now()->format('Ymd_His') . '.csv';
        $fmt      = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return response()->streamDownload(function () use ($motoristas, $fmt) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'CPF', 'Nascimento', 'Telefone', 'CNH', 'Categoria', 'Validade CNH', 'Situação'], ';');
            foreach ($motoristas as $m) {
                fputcsv($out, [
                    $m->mot_nome,
                    $m->mot_cpf ?? '',
                    $fmt($m->mot_dt_nascimento),
                    $m->mot_telefone ?? '',
                    $m->mot_cnh_numero,
                    $m->mot_cnh_categoria,
                    $fmt($m->mot_cnh_dt_validade),
                    $m->mot_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($motoristas, Request $request): HttpResponse
    {
        $collection = collect($motoristas);
        $filename   = 'transporte_motoristas_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_motoristas_pdf', [
            'motoristas'    => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('mot_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('mot_fl_ativo', false)->count(),
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
