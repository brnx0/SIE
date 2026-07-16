<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreItemRequest;
use App\Models\Alimentacao\CategoriaAlimento;
use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\TipoItem;
use App\Models\Alimentacao\UnidadeMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $itens = $this->baseQuery($request)
            ->with([
                'tipoItem:tpi_id,tpi_nome',
                'categoria:cat_id,cat_nome',
                'unidadeMedida:umd_id,umd_sigla',
            ])
            ->orderBy('itm_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('alimentacao/itens/Index', [
            'itens'      => $itens,
            'tipos'      => TipoItem::where('tpi_fl_ativo', true)->orderBy('tpi_nome')->get(['tpi_id', 'tpi_nome']),
            'categorias' => CategoriaAlimento::where('cat_fl_ativo', true)->orderBy('cat_nome')->get(['cat_id', 'cat_nome']),
            'filters'    => [
                'search'   => $request->input('search', ''),
                'tpi_id'   => $request->input('tpi_id', ''),
                'cat_id'   => $request->input('cat_id', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/itens/Create', $this->lookups());
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = Item::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('alimentacao.itens.create')->with('success', 'Item cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('alimentacao.itens.edit', $item->itm_id)->with('success', 'Item cadastrado com sucesso.');
    }

    public function edit(int $id): Response
    {
        $item = Item::findOrFail($id);

        return Inertia::render('alimentacao/itens/Edit', [
            'item'       => $item,
            'conversoes' => $this->conversoesDoItem($item),
        ] + $this->lookups($item));
    }

    public function update(StoreItemRequest $request, int $id): RedirectResponse
    {
        $item  = Item::findOrFail($id);
        $dados = $request->validated();

        $this->assertUnidadeBaseAlteravel($item, (int) $dados['itm_umd_id']);

        $item->update($dados);

        return to_route('alimentacao.itens.edit', $id)->with('success', 'Item atualizado com sucesso.');
    }

    /**
     * Trocar a unidade base com conversões cadastradas quebraria todas elas: o
     * destino delas é a unidade base antiga, então os fatores passariam a
     * converter para a unidade errada — sem erro nenhum, só saldo torto.
     * Exige remover as conversões antes (decisão consciente de quem cadastra).
     */
    private function assertUnidadeBaseAlteravel(Item $item, int $novaUmdId): void
    {
        if ($item->itm_umd_id === $novaUmdId) {
            return;
        }

        $qtd = ConversaoUnidade::where('cvu_itm_id', $item->itm_id)->count();

        if ($qtd === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'itm_umd_id' => "Este item tem {$qtd} conversão(ões) cadastrada(s) apontando para a unidade atual"
                .' ('.$item->unidadeMedida?->umd_sigla.'). Remova as conversões antes de trocar a unidade de controle,'
                .' senão os fatores passam a converter para a unidade errada.',
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->safeDelete(Item::findOrFail($id))
            ?? to_route('alimentacao.itens.index')->with('success', 'Item removido com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $itens = $this->baseQuery($request)
            ->with(['tipoItem:tpi_id,tpi_nome', 'categoria:cat_id,cat_nome', 'unidadeMedida:umd_id,umd_sigla'])
            ->orderBy('itm_nome')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($itens, $request)
            : $this->exportCsv($itens);
    }

    /** Conversões da embalagem para a unidade base do item (ex.: CX → 30 KG). */
    private function conversoesDoItem(Item $item)
    {
        return ConversaoUnidade::query()
            ->where('cvu_itm_id', $item->itm_id)
            ->with('unidadeOrigem:umd_id,umd_sigla,umd_nome')
            ->get()
            ->map(fn ($c) => [
                'cvu_id'            => $c->cvu_id,
                'cvu_umd_origem_id' => $c->cvu_umd_origem_id,
                'cvu_fator'         => (float) $c->cvu_fator,
                'cvu_fl_ativo'      => $c->cvu_fl_ativo,
                'origem_sigla'      => $c->unidadeOrigem?->umd_sigla,
                'origem_nome'       => $c->unidadeOrigem?->umd_nome,
            ]);
    }

    /**
     * Lookups do formulário. Passa `incluir_ids` do próprio registro para que uma
     * FK salva continue visível mesmo se o catálogo tiver sido inativado depois.
     */
    private function lookups(?Item $item = null): array
    {
        $tipos = TipoItem::query()->orderBy('tpi_nome');
        $this->filtroAtivoOuIncluso($tipos, 'tpi_fl_ativo', 'tpi_id', $item?->itm_tpi_id ? [$item->itm_tpi_id] : []);

        $categorias = CategoriaAlimento::query()->orderBy('cat_nome');
        $this->filtroAtivoOuIncluso($categorias, 'cat_fl_ativo', 'cat_id', $item?->itm_cat_id ? [$item->itm_cat_id] : []);

        $unidades = UnidadeMedida::query()->orderBy('umd_sigla');
        $this->filtroAtivoOuIncluso($unidades, 'umd_fl_ativo', 'umd_id', $item?->itm_umd_id ? [$item->itm_umd_id] : []);

        return [
            'tipos'      => $tipos->get(['tpi_id', 'tpi_nome']),
            'categorias' => $categorias->get(['cat_id', 'cat_nome']),
            'unidades'   => $unidades->get(['umd_id', 'umd_sigla', 'umd_nome']),
        ];
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');

        return Item::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('tpi_id'), fn ($q, $id) => $q->where('itm_tpi_id', (int) $id))
            ->when($request->input('cat_id'), fn ($q, $id) => $q->where('itm_cat_id', (int) $id))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('itm_fl_ativo', $situacao === 'ativo'));
    }

    private function exportCsv($itens): StreamedResponse
    {
        $filename = 'itens_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($itens) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Código', 'Item', 'Tipo', 'Categoria', 'Unidade', 'Perecível', 'Agric. Familiar', 'Validade (dias)', 'Estoque Mínimo', 'Situação'], ';');
            foreach ($itens as $i) {
                fputcsv($out, [
                    $i->itm_cd_item ?? '',
                    $i->itm_nome,
                    $i->tipoItem?->tpi_nome ?? '',
                    $i->categoria?->cat_nome ?? '',
                    $i->unidadeMedida?->umd_sigla ?? '',
                    $i->itm_fl_perecivel ? 'Sim' : 'Não',
                    $i->itm_fl_agricultura_familiar ? 'Sim' : 'Não',
                    $i->itm_validade_dias ?? '',
                    $i->itm_estoque_minimo ?? '',
                    $i->itm_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($itens, Request $request): HttpResponse
    {
        $collection = collect($itens);
        $filename   = 'itens_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_itens_pdf', [
            'itens'         => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('itm_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('itm_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
            'tipoFiltro'    => $request->input('tpi_id') ? TipoItem::find($request->input('tpi_id'))?->tpi_nome : '',
            'catFiltro'     => $request->input('cat_id') ? CategoriaAlimento::find($request->input('cat_id'))?->cat_nome : '',
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
