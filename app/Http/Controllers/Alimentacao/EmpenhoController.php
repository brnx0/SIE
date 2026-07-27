<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreEmpenhoRequest;
use App\Models\Alimentacao\Empenho;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpenhoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $empenhos = $this->baseQuery($request)
            ->with(['contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id', 'contrato.fornecedor:frn_id,frn_razao_social'])
            ->orderByDesc('emp_dt')
            ->paginate($perPage)
            ->withQueryString()
            // Saldo é calculado a partir das ordens — não é coluna.
            ->through(fn ($e) => [
                'emp_id'           => $e->emp_id,
                'emp_nr_empenho'   => $e->emp_nr_empenho,
                'emp_dt'           => $e->emp_dt?->toDateString(),
                'emp_vl_empenhado' => (float) $e->emp_vl_empenhado,
                'emp_situacao'     => $e->emp_situacao,
                'contrato_numero'  => $e->contrato?->ctr_nr_contrato,
                'contrato_ano'     => $e->contrato?->ctr_ano,
                'fornecedor_nome'  => $e->contrato?->fornecedor?->frn_razao_social,
                'vl_consumido'     => $e->valorConsumido(),
                'vl_saldo'         => $e->saldo(),
            ]);

        return Inertia::render('alimentacao/empenhos/Index', [
            'empenhos'  => $empenhos,
            'situacoes' => Empenho::SITUACOES,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/empenhos/Create', [
            'situacoes' => Empenho::SITUACOES,
        ]);
    }

    public function store(StoreEmpenhoRequest $request): RedirectResponse
    {
        $empenho = Empenho::create($request->validated());

        return to_route('alimentacao.empenhos.edit', $empenho->emp_id)
            ->with('success', 'Empenho cadastrado com sucesso.');
    }

    public function edit(int $id): Response
    {
        $empenho = Empenho::with([
            'contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id',
            'contrato.fornecedor:frn_id,frn_razao_social',
            'ordens:ofn_id,ofn_emp_id,ofn_nr_ordem,ofn_dt_emissao,ofn_situacao',
        ])->findOrFail($id);

        return Inertia::render('alimentacao/empenhos/Edit', [
            'empenho'   => $empenho,
            'situacoes' => Empenho::SITUACOES,
            'saldo'     => [
                'consumido' => $empenho->valorConsumido(),
                'saldo'     => $empenho->saldo(),
            ],
            'ordens' => $empenho->ordens->map(fn ($o) => [
                'ofn_id'         => $o->ofn_id,
                'ofn_nr_ordem'   => $o->ofn_nr_ordem,
                'ofn_dt_emissao' => $o->ofn_dt_emissao?->toDateString(),
                'ofn_situacao'   => $o->ofn_situacao,
            ]),
        ]);
    }

    public function update(StoreEmpenhoRequest $request, int $id): RedirectResponse
    {
        $empenho = Empenho::findOrFail($id);
        $dados   = $request->validated();

        $this->assertValorCobreOrdens($empenho, (float) $dados['emp_vl_empenhado']);
        $this->assertContratoNaoMudaComOrdens($empenho, (int) $dados['emp_ctr_id']);

        $empenho->update($dados);

        return to_route('alimentacao.empenhos.edit', $id)->with('success', 'Empenho atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $empenho = Empenho::findOrFail($id);

        if ($empenho->ordens()->exists()) {
            return back()->with('error', 'Este empenho já custeia ordens de fornecimento e não pode ser excluído. Cancele-o mudando a situação.');
        }

        return $this->safeDelete($empenho)
            ?? to_route('alimentacao.empenhos.index')->with('success', 'Empenho removido com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $empenhos = $this->baseQuery($request)
            ->with(['contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id', 'contrato.fornecedor:frn_id,frn_razao_social'])
            ->orderByDesc('emp_dt')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($empenhos, $request)
            : $this->exportCsv($empenhos);
    }

    /**
     * Reduzir o empenho abaixo do que as ordens já consomem deixaria o saldo
     * negativo retroativamente — despesa sem cobertura orçamentária, sem
     * ninguém ter emitido nada.
     */
    private function assertValorCobreOrdens(Empenho $empenho, float $novoValor): void
    {
        $consumido = $empenho->valorConsumido();

        if ($novoValor >= $consumido) {
            return;
        }

        throw ValidationException::withMessages([
            'emp_vl_empenhado' => 'As ordens de fornecimento deste empenho já consomem R$ '
                .number_format($consumido, 2, ',', '.')
                .'. O valor empenhado não pode ficar abaixo disso.',
        ]);
    }

    /** Trocar o contrato com ordens emitidas desvincularia a despesa da origem. */
    private function assertContratoNaoMudaComOrdens(Empenho $empenho, int $novoContratoId): void
    {
        if ($empenho->emp_ctr_id === $novoContratoId || ! $empenho->ordens()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'emp_ctr_id' => 'Este empenho já custeia ordens de fornecimento — o contrato não pode ser trocado.',
        ]);
    }

    private function baseQuery(Request $request)
    {
        return Empenho::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('ctr_id'), fn ($q, $id) => $q->where('emp_ctr_id', (int) $id))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('emp_situacao', $s));
    }

    private function exportCsv($empenhos): StreamedResponse
    {
        $filename = 'empenhos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($empenhos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Empenho', 'Data', 'Contrato', 'Fornecedor', 'Valor Empenhado', 'Consumido', 'Saldo', 'Situação'], ';');

            foreach ($empenhos as $e) {
                fputcsv($out, [
                    $e->emp_nr_empenho,
                    $e->emp_dt->format('d/m/Y'),
                    $e->contrato ? $e->contrato->ctr_nr_contrato.'/'.$e->contrato->ctr_ano : '',
                    $e->contrato?->fornecedor?->frn_razao_social ?? '',
                    number_format((float) $e->emp_vl_empenhado, 2, ',', '.'),
                    number_format($e->valorConsumido(), 2, ',', '.'),
                    number_format($e->saldo(), 2, ',', '.'),
                    $e->emp_situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($empenhos, Request $request): HttpResponse
    {
        $collection = collect($empenhos);
        $filename   = 'empenhos_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_empenhos_pdf', [
            'empenhos'   => $collection,
            'total'      => $collection->count(),
            'valorTotal' => $collection->sum(fn ($e) => (float) $e->emp_vl_empenhado),
            'saldoTotal' => $collection->sum(fn ($e) => $e->saldo()),
            'search'     => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
