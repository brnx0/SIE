<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreUnidadeMedidaRequest;
use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\UnidadeMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnidadeMedidaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alimentacao/unidades-medida/Index', [
            'unidades' => $this->baseQuery($request)->orderBy('umd_sigla')->paginate($perPage)->withQueryString(),
            'filters'  => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/unidades-medida/Create');
    }

    public function store(StoreUnidadeMedidaRequest $request): RedirectResponse
    {
        $unidade = UnidadeMedida::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('alimentacao.unidades-medida.create')
                ->with('success', 'Unidade de medida cadastrada. Pronto para o próximo cadastro.');
        }

        return to_route('alimentacao.unidades-medida.edit', $unidade->umd_id)
            ->with('success', 'Unidade de medida cadastrada com sucesso.');
    }

    public function edit(int $id): Response
    {
        $unidade = UnidadeMedida::findOrFail($id);

        return Inertia::render('alimentacao/unidades-medida/Edit', [
            'unidade' => $unidade,
            // Conversões globais que partem desta unidade. Regra de item vive na tela do Item.
            'conversoes' => ConversaoUnidade::query()
                ->whereNull('cvu_itm_id')
                ->where('cvu_umd_origem_id', $id)
                ->with('unidadeDestino:umd_id,umd_sigla,umd_nome')
                ->get()
                ->map(fn ($c) => [
                    'cvu_id'             => $c->cvu_id,
                    'cvu_umd_destino_id' => $c->cvu_umd_destino_id,
                    'cvu_fator'          => (float) $c->cvu_fator,
                    'cvu_fl_ativo'       => $c->cvu_fl_ativo,
                    'destino_sigla'      => $c->unidadeDestino?->umd_sigla,
                    'destino_nome'       => $c->unidadeDestino?->umd_nome,
                ]),
            'unidadesDestino' => UnidadeMedida::query()
                ->where('umd_fl_ativo', true)
                ->where('umd_id', '!=', $id)
                ->orderBy('umd_sigla')
                ->get(['umd_id', 'umd_sigla', 'umd_nome']),
        ]);
    }

    public function update(StoreUnidadeMedidaRequest $request, int $id): RedirectResponse
    {
        UnidadeMedida::findOrFail($id)->update($request->validated());

        return to_route('alimentacao.unidades-medida.edit', $id)
            ->with('success', 'Unidade de medida atualizada com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->safeDelete(UnidadeMedida::findOrFail($id))
            ?? to_route('alimentacao.unidades-medida.index')->with('success', 'Unidade de medida removida com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $unidades = $this->baseQuery($request)->orderBy('umd_sigla')->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($unidades, $request)
            : $this->exportCsv($unidades);
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');

        return UnidadeMedida::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('umd_fl_ativo', $situacao === 'ativo'));
    }

    private function exportCsv($unidades): StreamedResponse
    {
        $filename = 'unidades_medida_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($unidades) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Sigla', 'Nome', 'Aceita Fração', 'Situação'], ';');
            foreach ($unidades as $u) {
                fputcsv($out, [
                    $u->umd_sigla,
                    $u->umd_nome,
                    $u->umd_fl_fracionavel ? 'Sim' : 'Não',
                    $u->umd_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($unidades, Request $request): HttpResponse
    {
        $collection = collect($unidades);
        $filename   = 'unidades_medida_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_unidades_medida_pdf', [
            'unidades'      => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('umd_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('umd_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
