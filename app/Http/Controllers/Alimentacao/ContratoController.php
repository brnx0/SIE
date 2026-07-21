<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Concerns\ComAnexos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreContratoRequest;
use App\Models\Alimentacao\Anexo;
use App\Models\Alimentacao\Contrato;
use App\Models\Alimentacao\ContratoAditivo;
use App\Models\Alimentacao\UnidadeMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContratoController extends Controller
{
    use ComAnexos;

    /** Campos que, depois do contrato ter movimento, só mudam via aditivo. */
    private const CAMPOS_SO_POR_ADITIVO = [
        'ctr_vl_total' => 'valor total',
        'ctr_dt_fim'   => 'fim da vigência',
        'ctr_objeto'   => 'objeto',
    ];

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alimentacao/contratos/Index', [
            'contratos' => $this->baseQuery($request)
                ->with('fornecedor:frn_id,frn_razao_social,frn_cnpj,frn_cpf')
                ->withCount(['itens', 'aditivos', 'ordens'])
                ->orderByDesc('ctr_ano')
                ->orderByDesc('ctr_nr_contrato')
                ->paginate($perPage)
                ->withQueryString(),
            'situacoes' => Contrato::SITUACOES,
            'filters'   => [
                'search'   => $request->input('search', ''),
                'ano'      => $request->input('ano', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/contratos/Create', [
            'situacoes' => Contrato::SITUACOES,
        ]);
    }

    public function store(StoreContratoRequest $request): RedirectResponse
    {
        $contrato = Contrato::create($request->validated());

        return to_route('alimentacao.contratos.edit', $contrato->ctr_id)
            ->with('success', 'Contrato cadastrado. Agora informe os itens contratados.');
    }

    public function edit(int $id): Response
    {
        $contrato = Contrato::with('fornecedor:frn_id,frn_razao_social,frn_cnpj,frn_cpf')->findOrFail($id);

        return Inertia::render('alimentacao/contratos/Edit', [
            'contrato' => $contrato,
            'itens'    => $this->itensDoContrato($contrato),
            'aditivos' => $this->aditivosDoContrato($contrato),
            'anexos'   => $this->anexosPara($contrato),
            'unidades' => UnidadeMedida::where('umd_fl_ativo', true)->orderBy('umd_sigla')->get(['umd_id', 'umd_sigla', 'umd_nome']),
            'situacoes' => Contrato::SITUACOES,
            'tiposAditivo' => ContratoAditivo::TIPOS,
            // Com movimento no contrato, a tela trava valor/prazo/objeto e manda usar aditivo.
            'temMovimento' => $this->temMovimento($contrato),
            'totais'       => [
                'valor_itens'   => (float) $contrato->itens()->sum(\Illuminate\Support\Facades\DB::raw('cti_qtd * cti_vl_unitario')),
                'valor_ordens'  => $this->valorEmOrdens($contrato),
            ],
        ]);
    }

    public function update(StoreContratoRequest $request, int $id): RedirectResponse
    {
        $contrato = Contrato::findOrFail($id);
        $dados    = $request->validated();

        $this->assertCamposSoPorAditivo($contrato, $dados);

        $contrato->update($dados);

        return to_route('alimentacao.contratos.edit', $id)->with('success', 'Contrato atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $contrato = Contrato::findOrFail($id);

        if ($this->temMovimento($contrato)) {
            return back()->with('error', 'Este contrato já tem aditivos ou ordens de fornecimento e não pode ser excluído. Altere a situação para Encerrado ou Rescindido.');
        }

        $contrato->itens()->delete();

        return $this->safeDelete($contrato)
            ?? to_route('alimentacao.contratos.index')->with('success', 'Contrato removido com sucesso.');
    }

    // ---- Anexos (PDF/imagem do contrato) ----------------------------------

    public function anexosStore(Request $request, int $id): RedirectResponse
    {
        $this->guardarAnexos($request, Contrato::findOrFail($id), 'contratos');

        return back()->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    public function anexoDownload(int $id, Anexo $anexo): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->baixarAnexoDe(Contrato::findOrFail($id), $anexo);
    }

    public function anexoDestroy(int $id, Anexo $anexo): RedirectResponse
    {
        $this->excluirAnexoDe(Contrato::findOrFail($id), $anexo);

        return back()->with('success', 'Anexo removido.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $contratos = $this->baseQuery($request)
            ->with(['fornecedor:frn_id,frn_razao_social,frn_cnpj,frn_cpf', 'itens.item:itm_id,itm_nome', 'itens.unidadeMedida:umd_id,umd_sigla'])
            ->orderByDesc('ctr_ano')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($contratos, $request)
            : $this->exportCsv($contratos);
    }

    /**
     * Contrato com aditivo ou ordem já emitida não pode ter valor/prazo/objeto
     * editados direto: o aditivo é o log dessas mudanças, e alterar por fora
     * faria o histórico mentir sobre o que o contrato já foi.
     *
     * Antes do primeiro movimento a edição é livre — é a janela para corrigir
     * erro de digitação sem inventar um aditivo que não existiu.
     */
    private function assertCamposSoPorAditivo(Contrato $contrato, array $dados): void
    {
        if (! $this->temMovimento($contrato)) {
            return;
        }

        $mudou = [];

        foreach (self::CAMPOS_SO_POR_ADITIVO as $campo => $rotulo) {
            $atual = $contrato->{$campo};
            $novo  = $dados[$campo] ?? null;

            $diferente = match ($campo) {
                'ctr_vl_total' => (float) $atual !== (float) $novo,
                'ctr_dt_fim'   => $atual?->toDateString() !== $novo,
                default        => (string) $atual !== (string) $novo,
            };

            if ($diferente) {
                $mudou[$campo] = $rotulo;
            }
        }

        if (empty($mudou)) {
            return;
        }

        throw ValidationException::withMessages(
            collect($mudou)->mapWithKeys(fn ($rotulo, $campo) => [
                $campo => "Este contrato já tem aditivos ou ordens de fornecimento — o {$rotulo} só pode ser alterado lançando um aditivo, que é o que registra o histórico da mudança.",
            ])->all(),
        );
    }

    /**
     * Aditivo cancelado não conta: o contrato voltou ao que era antes dele, então
     * a edição direta volta a ser segura (não há alteração viva para o log
     * contradizer).
     */
    private function temMovimento(Contrato $contrato): bool
    {
        return $contrato->aditivos()->validos()->exists() || $contrato->ordens()->exists();
    }

    private function ehUltimoValidoDoTipo(ContratoAditivo $aditivo): bool
    {
        return ! ContratoAditivo::query()
            ->where('adt_ctr_id', $aditivo->adt_ctr_id)
            ->where('adt_tipo', $aditivo->adt_tipo)
            ->validos()
            ->when($aditivo->adt_tipo === ContratoAditivo::TIPO_QTD,
                fn ($q) => $q->where('adt_cti_id', $aditivo->adt_cti_id))
            ->where('adt_id', '>', $aditivo->adt_id)
            ->exists();
    }

    private function valorEmOrdens(Contrato $contrato): float
    {
        return (float) \Illuminate\Support\Facades\DB::table('ali_ordem_fornecimento_item as ofi')
            ->join('ali_ordem_fornecimento as ofn', 'ofn.ofn_id', '=', 'ofi.ofi_ofn_id')
            ->where('ofn.ofn_ctr_id', $contrato->ctr_id)
            ->where('ofn.ofn_situacao', '!=', 'CANCELADA')
            ->whereNull('ofn.ofn_deleted_at')
            ->sum(\Illuminate\Support\Facades\DB::raw('ofi.ofi_qtd * ofi.ofi_vl_unitario'));
    }

    private function itensDoContrato(Contrato $contrato)
    {
        return $contrato->itens()
            ->with(['item:itm_id,itm_nome', 'unidadeMedida:umd_id,umd_sigla'])
            ->get()
            ->map(fn ($i) => [
                'cti_id'          => $i->cti_id,
                'cti_itm_id'      => $i->cti_itm_id,
                'cti_umd_id'      => $i->cti_umd_id,
                'cti_qtd'         => (float) $i->cti_qtd,
                'cti_vl_unitario' => (float) $i->cti_vl_unitario,
                'item_nome'       => $i->item?->itm_nome,
                'umd_sigla'       => $i->unidadeMedida?->umd_sigla,
                // Saldo é calculado (não é coluna) — sem isso a tela não mostra
                // quanto ainda dá para pedir.
                'qtd_pedida'      => $i->qtdPedida(),
                'qtd_saldo'       => $i->qtdSaldo(),
                'vl_total'        => (float) $i->cti_qtd * (float) $i->cti_vl_unitario,
            ]);
    }

    private function aditivosDoContrato(Contrato $contrato)
    {
        return $contrato->aditivos()
            ->with(['usuario:id,name', 'usuarioCancelamento:id,name', 'contratoItem.item:itm_id,itm_nome'])
            ->orderByDesc('adt_dt_assinatura')
            ->orderByDesc('adt_id')
            ->get()
            ->map(fn ($a) => [
                'adt_id'              => $a->adt_id,
                'adt_nr_aditivo'      => $a->adt_nr_aditivo,
                'adt_tipo'            => $a->adt_tipo,
                'adt_dt_assinatura'   => $a->adt_dt_assinatura?->toDateString(),
                'adt_vl_anterior'     => $a->adt_vl_anterior !== null ? (float) $a->adt_vl_anterior : null,
                'adt_vl_novo'         => $a->adt_vl_novo !== null ? (float) $a->adt_vl_novo : null,
                'adt_dt_fim_anterior' => $a->adt_dt_fim_anterior?->toDateString(),
                'adt_dt_fim_nova'     => $a->adt_dt_fim_nova?->toDateString(),
                'adt_objeto_anterior' => $a->adt_objeto_anterior,
                'adt_objeto_novo'     => $a->adt_objeto_novo,
                'adt_qtd_anterior'    => $a->adt_qtd_anterior !== null ? (float) $a->adt_qtd_anterior : null,
                'adt_qtd_nova'        => $a->adt_qtd_nova !== null ? (float) $a->adt_qtd_nova : null,
                'adt_justificativa'   => $a->adt_justificativa,
                'item_nome'           => $a->contratoItem?->item?->itm_nome,
                'usuario_nome'        => $a->usuario?->name,
                'adt_fl_cancelado'               => $a->adt_fl_cancelado,
                'adt_dt_cancelamento'            => $a->adt_dt_cancelamento?->toDateString(),
                'adt_justificativa_cancelamento' => $a->adt_justificativa_cancelamento,
                'usuario_cancelamento_nome'      => $a->usuarioCancelamento?->name,
                // Só o último válido de cada tipo pode ser cancelado: cancelar do
                // meio deixaria indefinido para qual valor o contrato volta.
                'pode_cancelar' => ! $a->adt_fl_cancelado && $this->ehUltimoValidoDoTipo($a),
            ]);
    }

    private function baseQuery(Request $request)
    {
        return Contrato::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('ano'), fn ($q, $a) => $q->where('ctr_ano', (int) $a))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('ctr_situacao', $s));
    }

    private function exportCsv($contratos): StreamedResponse
    {
        $filename = 'contratos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($contratos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Contrato', 'Ano', 'Fornecedor', 'Objeto', 'Modalidade', 'Vigência', 'Valor Total', 'Situação', 'Itens'], ';');

            foreach ($contratos as $c) {
                $itens = $c->itens
                    ->map(fn ($i) => $i->item?->itm_nome.' ('.rtrim(rtrim(number_format((float) $i->cti_qtd, 4, ',', '.'), '0'), ',').' '.$i->unidadeMedida?->umd_sigla.')')
                    ->implode(' | ');

                fputcsv($out, [
                    $c->ctr_nr_contrato,
                    $c->ctr_ano,
                    $c->fornecedor?->frn_razao_social ?? '',
                    $c->ctr_objeto,
                    $c->ctr_modalidade ?? '',
                    $c->ctr_dt_inicio->format('d/m/Y').' a '.$c->ctr_dt_fim->format('d/m/Y'),
                    number_format((float) $c->ctr_vl_total, 2, ',', '.'),
                    $c->ctr_situacao,
                    $itens,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($contratos, Request $request): HttpResponse
    {
        $collection = collect($contratos);
        $filename   = 'contratos_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_contratos_pdf', [
            'contratos' => $collection,
            'total'     => $collection->count(),
            'valorTotal' => $collection->sum(fn ($c) => (float) $c->ctr_vl_total),
            'search'    => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
