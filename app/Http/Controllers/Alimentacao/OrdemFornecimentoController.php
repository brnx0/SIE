<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreOrdemFornecimentoRequest;
use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\Empenho;
use App\Models\Alimentacao\OrdemFornecimento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdemFornecimentoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $ordens = $this->baseQuery($request)
            ->with([
                'contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id',
                'contrato.fornecedor:frn_id,frn_razao_social',
                'empenho:emp_id,emp_nr_empenho',
                'itens',
            ])
            ->orderByDesc('ofn_dt_emissao')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($o) => [
                'ofn_id'                  => $o->ofn_id,
                'ofn_nr_ordem'            => $o->ofn_nr_ordem,
                'ofn_dt_emissao'          => $o->ofn_dt_emissao?->toDateString(),
                'ofn_dt_entrega_prevista' => $o->ofn_dt_entrega_prevista?->toDateString(),
                'ofn_situacao'            => $o->ofn_situacao,
                'contrato_numero'         => $o->contrato?->ctr_nr_contrato,
                'contrato_ano'            => $o->contrato?->ctr_ano,
                'fornecedor_nome'         => $o->contrato?->fornecedor?->frn_razao_social,
                'empenho_numero'          => $o->empenho?->emp_nr_empenho,
                'itens_count'             => $o->itens->count(),
                'vl_total'                => $o->valorTotal(),
            ]);

        return Inertia::render('alimentacao/ordens-fornecimento/Index', [
            'ordens'    => $ordens,
            'situacoes' => OrdemFornecimento::SITUACOES,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/ordens-fornecimento/Create', $this->lookups());
    }

    public function store(StoreOrdemFornecimentoRequest $request): RedirectResponse
    {
        $ordem = OrdemFornecimento::create($request->validated());

        return to_route('alimentacao.ordens-fornecimento.edit', $ordem->ofn_id)
            ->with('success', 'Ordem de fornecimento cadastrada. Agora informe os itens pedidos.');
    }

    public function edit(int $id): Response
    {
        $ordem = OrdemFornecimento::with([
            'contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id,ctr_situacao,ctr_dt_inicio,ctr_dt_fim',
            'contrato.fornecedor:frn_id,frn_razao_social',
            'empenho:emp_id,emp_nr_empenho,emp_vl_empenhado',
        ])->findOrFail($id);

        return Inertia::render('alimentacao/ordens-fornecimento/Edit', [
            'ordem' => $ordem,
            'itens' => $this->itensDaOrdem($ordem),
            // Itens do contrato com o saldo disponível — é o que a tela oferece
            // para pedir, já mostrando quanto ainda cabe.
            'itensContrato' => $this->itensDoContratoComSaldo($ordem),
            'saldoEmpenho'  => $ordem->empenho ? [
                'empenhado' => (float) $ordem->empenho->emp_vl_empenhado,
                'consumido' => $ordem->empenho->valorConsumido(),
                'saldo'     => $ordem->empenho->saldo(),
            ] : null,
            'vlTotal' => $ordem->valorTotal(),
        ] + $this->lookups($ordem));
    }

    public function update(StoreOrdemFornecimentoRequest $request, int $id): RedirectResponse
    {
        OrdemFornecimento::findOrFail($id)->update($request->validated());

        return to_route('alimentacao.ordens-fornecimento.edit', $id)
            ->with('success', 'Ordem de fornecimento atualizada com sucesso.');
    }

    /**
     * Cancelar é a única transição de situação que parte de decisão de gente —
     * EMITIDA/PARCIAL/ENTREGUE saem sozinhas do recebimento.
     */
    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $ordem = OrdemFornecimento::findOrFail($id);

        $dados = $request->validate(
            ['ofn_motivo_cancelamento' => ['required', 'string', 'min:5']],
            ['ofn_motivo_cancelamento.required' => 'Informe o motivo do cancelamento.'],
            ['ofn_motivo_cancelamento' => 'motivo do cancelamento'],
        );

        if ($ordem->ofn_situacao === OrdemFornecimento::SITUACAO_CANCELADA) {
            return back()->withErrors(['ofn_motivo_cancelamento' => 'Esta ordem já está cancelada.']);
        }

        // Entrega registrada já virou estoque: cancelar aqui faria a ordem
        // dizer que nada aconteceu enquanto o razão mostra a entrada. Para
        // desfazer isso, o caminho é estornar o recebimento.
        if ($ordem->itens()->where('ofi_qtd_entregue', '>', 0)->exists()) {
            return back()->withErrors([
                'ofn_motivo_cancelamento' => 'Esta ordem já tem entrega registrada no estoque e não pode ser cancelada. Estorne o recebimento antes.',
            ]);
        }

        $ordem->update([
            'ofn_situacao'            => OrdemFornecimento::SITUACAO_CANCELADA,
            'ofn_motivo_cancelamento' => $dados['ofn_motivo_cancelamento'],
        ]);

        return back()->with('success', 'Ordem de fornecimento cancelada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $ordem = OrdemFornecimento::findOrFail($id);

        // Entrega registrada já mexeu no estoque: apagar deixaria o saldo mentindo.
        if ($ordem->itens()->where('ofi_qtd_entregue', '>', 0)->exists()) {
            return back()->with('error', 'Esta ordem já tem entregas registradas e não pode ser excluída. Estorne o recebimento antes.');
        }

        $ordem->itens()->delete();

        return $this->safeDelete($ordem)
            ?? to_route('alimentacao.ordens-fornecimento.index')->with('success', 'Ordem de fornecimento removida com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $ordens = $this->baseQuery($request)
            ->with([
                'contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id',
                'contrato.fornecedor:frn_id,frn_razao_social',
                'empenho:emp_id,emp_nr_empenho',
                'itens.item:itm_id,itm_nome',
                'itens.unidadeMedida:umd_id,umd_sigla',
            ])
            ->orderByDesc('ofn_dt_emissao')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($ordens, $request)
            : $this->exportCsv($ordens);
    }

    private function itensDaOrdem(OrdemFornecimento $ordem)
    {
        return $ordem->itens()
            ->with(['item:itm_id,itm_nome', 'unidadeMedida:umd_id,umd_sigla'])
            ->get()
            ->map(fn ($i) => [
                'ofi_id'           => $i->ofi_id,
                'ofi_itm_id'       => $i->ofi_itm_id,
                'ofi_qtd'          => (float) $i->ofi_qtd,
                'ofi_vl_unitario'  => (float) $i->ofi_vl_unitario,
                'ofi_qtd_entregue' => (float) $i->ofi_qtd_entregue,
                'item_nome'        => $i->item?->itm_nome,
                'umd_sigla'        => $i->unidadeMedida?->umd_sigla,
                'vl_total'         => $i->valorTotal(),
            ]);
    }

    private function itensDoContratoComSaldo(OrdemFornecimento $ordem)
    {
        $contrato = $ordem->contrato;

        if (! $contrato) {
            return collect();
        }

        return $contrato->itens()
            ->with(['item:itm_id,itm_nome', 'unidadeMedida:umd_id,umd_sigla'])
            ->get()
            ->map(fn ($i) => [
                'cti_id'          => $i->cti_id,
                'cti_itm_id'      => $i->cti_itm_id,
                'item_nome'       => $i->item?->itm_nome,
                'umd_sigla'       => $i->unidadeMedida?->umd_sigla,
                'cti_qtd'         => (float) $i->cti_qtd,
                'cti_vl_unitario' => (float) $i->cti_vl_unitario,
                'qtd_saldo'       => $i->qtdSaldo(),
            ]);
    }

    private function lookups(?OrdemFornecimento $ordem = null): array
    {
        return [
            'contratos' => Contrato::query()
                ->where(fn ($q) => $q->where('ctr_situacao', Contrato::SITUACAO_VIGENTE)
                    ->when($ordem?->ofn_ctr_id, fn ($q2, $id) => $q2->orWhere('ctr_id', $id)))
                ->with('fornecedor:frn_id,frn_razao_social')
                ->orderByDesc('ctr_ano')
                ->get(['ctr_id', 'ctr_nr_contrato', 'ctr_ano', 'ctr_frn_id', 'ctr_situacao']),
            'empenhos' => Empenho::query()
                ->where('emp_situacao', Empenho::SITUACAO_ATIVO)
                ->orderByDesc('emp_dt')
                ->get(['emp_id', 'emp_ctr_id', 'emp_nr_empenho', 'emp_vl_empenhado']),
        ];
    }

    private function baseQuery(Request $request)
    {
        return OrdemFornecimento::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('ctr_id'), fn ($q, $id) => $q->where('ofn_ctr_id', (int) $id))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('ofn_situacao', $s));
    }

    private function exportCsv($ordens): StreamedResponse
    {
        $filename = 'ordens_fornecimento_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($ordens) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Ordem', 'Emissão', 'Contrato', 'Fornecedor', 'Empenho', 'Situação', 'Item', 'Qtd', 'Unid.', 'Vl. Unitário', 'Vl. Total'], ';');

            foreach ($ordens as $o) {
                $cabecalho = [
                    $o->ofn_nr_ordem,
                    $o->ofn_dt_emissao->format('d/m/Y'),
                    $o->contrato ? $o->contrato->ctr_nr_contrato.'/'.$o->contrato->ctr_ano : '',
                    $o->contrato?->fornecedor?->frn_razao_social ?? '',
                    $o->empenho?->emp_nr_empenho ?? '',
                    $o->ofn_situacao,
                ];

                if ($o->itens->isEmpty()) {
                    fputcsv($out, array_merge($cabecalho, ['SEM ITENS', '', '', '', '']), ';');
                    continue;
                }

                foreach ($o->itens as $i) {
                    fputcsv($out, array_merge($cabecalho, [
                        $i->item?->itm_nome ?? '',
                        rtrim(rtrim(number_format((float) $i->ofi_qtd, 4, ',', '.'), '0'), ','),
                        $i->unidadeMedida?->umd_sigla ?? '',
                        number_format((float) $i->ofi_vl_unitario, 4, ',', '.'),
                        number_format($i->valorTotal(), 2, ',', '.'),
                    ]), ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($ordens, Request $request): HttpResponse
    {
        $collection = collect($ordens);
        $filename   = 'ordens_fornecimento_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_ordens_pdf', [
            'ordens'     => $collection,
            'total'      => $collection->count(),
            'valorTotal' => $collection->sum(fn ($o) => $o->valorTotal()),
            'search'     => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
