<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreCardapioRequest;
use App\Models\Alimentacao\Cardapio;
use App\Models\Alimentacao\CardapioDia;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Turma\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CardapioController extends Controller
{
    /** Refeições servidas na rede. Vira catálogo se o cliente precisar de mais. */
    public const REFEICOES = ['LANCHE DA MANHA', 'ALMOCO', 'LANCHE DA TARDE', 'JANTAR'];

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alimentacao/cardapios/Index', [
            'cardapios' => $this->baseQuery($request)
                ->with(['escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano'])
                ->withCount('dias')
                ->orderByDesc('crd_dt_inicio')
                ->paginate($perPage)
                ->withQueryString(),
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'filters'     => [
                'search'   => $request->input('search', ''),
                'anl_id'   => $request->input('anl_id', ''),
                'escopo'   => $request->input('escopo', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/cardapios/Create', $this->lookups());
    }

    public function store(StoreCardapioRequest $request): RedirectResponse
    {
        $cardapio = Cardapio::create($request->validated());

        return to_route('alimentacao.cardapios.edit', $cardapio->crd_id)
            ->with('success', 'Cardápio cadastrado. Agora monte a grade de pratos.');
    }

    public function edit(int $id): Response
    {
        $cardapio = Cardapio::with(['escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano'])->findOrFail($id);

        return Inertia::render('alimentacao/cardapios/Edit', [
            'cardapio' => $cardapio,
            'dias'     => $this->diasDoCardapio($cardapio),
            'turnos'   => Turma::TURNOS,
            'refeicoes' => self::REFEICOES,
        ] + $this->lookups($cardapio));
    }

    public function update(StoreCardapioRequest $request, int $id): RedirectResponse
    {
        Cardapio::findOrFail($id)->update($request->validated());

        return to_route('alimentacao.cardapios.edit', $id)->with('success', 'Cardápio atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $cardapio = Cardapio::findOrFail($id);

        // A grade é parte do cardápio (cascade no banco): remove junto.
        $cardapio->dias()->delete();

        return $this->safeDelete($cardapio)
            ?? to_route('alimentacao.cardapios.index')->with('success', 'Cardápio removido com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $cardapios = $this->baseQuery($request)
            ->with(['escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano', 'dias.prato:prt_id,prt_nome'])
            ->orderByDesc('crd_dt_inicio')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($cardapios, $request)
            : $this->exportCsv($cardapios);
    }

    private function diasDoCardapio(Cardapio $cardapio)
    {
        return $cardapio->dias()
            ->with('prato:prt_id,prt_nome')
            ->withCount([])
            ->get()
            ->map(fn ($d) => [
                'cdi_id'         => $d->cdi_id,
                'cdi_prt_id'     => $d->cdi_prt_id,
                'cdi_semana'     => $d->cdi_semana,
                'cdi_dia_semana' => $d->cdi_dia_semana,
                'cdi_turno'      => $d->cdi_turno,
                'cdi_refeicao'   => $d->cdi_refeicao,
                'prato_nome'     => $d->prato?->prt_nome,
            ]);
    }

    /**
     * A escola vem do EscolaCombobox (busca em /api/escolas, já escopado por
     * lotação e com `incluir_ids` para a que estiver salva) — não há lista aqui.
     */
    private function lookups(?Cardapio $cardapio = null): array
    {
        return [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
        ];
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');
        $escopo   = $request->input('escopo');

        return Cardapio::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('anl_id'), fn ($q, $id) => $q->where('crd_anl_id', (int) $id))
            ->when($escopo === 'rede', fn ($q) => $q->whereNull('crd_esc_id'))
            ->when($escopo === 'escola', fn ($q) => $q->whereNotNull('crd_esc_id'))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('crd_fl_ativo', $situacao === 'ativo'));
    }

    private function exportCsv($cardapios): StreamedResponse
    {
        $filename = 'cardapios_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($cardapios) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Cardápio', 'Escopo', 'Ano', 'Vigência', 'Semana', 'Dia', 'Turno', 'Refeição', 'Prato'], ';');

            foreach ($cardapios as $c) {
                $escopo   = $c->escola?->esc_nome ?? 'PADRÃO DA REDE';
                $vigencia = $c->crd_dt_inicio->format('d/m/Y').' a '.$c->crd_dt_fim->format('d/m/Y');

                if ($c->dias->isEmpty()) {
                    fputcsv($out, [$c->crd_nome, $escopo, $c->anoLetivo?->anl_ano, $vigencia, '', '', '', '', 'GRADE NÃO MONTADA'], ';');
                    continue;
                }

                foreach ($c->dias->sortBy(['cdi_semana', 'cdi_dia_semana', 'cdi_turno']) as $d) {
                    fputcsv($out, [
                        $c->crd_nome, $escopo, $c->anoLetivo?->anl_ano, $vigencia,
                        $d->cdi_semana, CardapioDia::nomeDia($d->cdi_dia_semana),
                        $d->cdi_turno, $d->cdi_refeicao, $d->prato?->prt_nome ?? '',
                    ], ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($cardapios, Request $request): HttpResponse
    {
        $collection = collect($cardapios);
        $filename   = 'cardapios_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_cardapios_pdf', [
            'cardapios' => $collection,
            'total'     => $collection->count(),
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
