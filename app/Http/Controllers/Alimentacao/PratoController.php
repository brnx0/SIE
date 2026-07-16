<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StorePratoRequest;
use App\Models\Alimentacao\Prato;
use App\Models\Alimentacao\UnidadeMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PratoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alimentacao/pratos/Index', [
            'pratos' => $this->baseQuery($request)
                ->withCount('itens')
                // Composição na listagem: quem monta cardápio precisa ver o que
                // tem no prato sem abrir cada um.
                ->with(['itens.item:itm_id,itm_nome'])
                ->orderBy('prt_nome')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn ($p) => [
                    'prt_id'        => $p->prt_id,
                    'prt_nome'      => $p->prt_nome,
                    'prt_descricao' => $p->prt_descricao,
                    'prt_fl_ativo'  => $p->prt_fl_ativo,
                    'itens_count'   => $p->itens_count,
                    'composicao'    => $p->itens->map(fn ($i) => $i->item?->itm_nome)->filter()->values(),
                    'tem_nutricional' => $p->temTabelaNutricional(),
                ]),
            'filters' => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/pratos/Create', [
            'unidades' => $this->unidadesAtivas(),
        ]);
    }

    private function unidadesAtivas()
    {
        return UnidadeMedida::where('umd_fl_ativo', true)
            ->orderBy('umd_sigla')
            ->get(['umd_id', 'umd_sigla', 'umd_nome']);
    }

    public function store(StorePratoRequest $request): RedirectResponse
    {
        $prato = Prato::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('alimentacao.pratos.create')->with('success', 'Prato cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('alimentacao.pratos.edit', $prato->prt_id)
            ->with('success', 'Prato cadastrado. Agora informe os itens da ficha técnica.');
    }

    public function edit(int $id): Response
    {
        $prato = Prato::with('porcaoUnidadeMedida:umd_id,umd_sigla')->findOrFail($id);

        return Inertia::render('alimentacao/pratos/Edit', [
            'prato' => $prato,
            'itens' => $this->itensDoPrato($prato),
            // Serve ao per capita da ficha técnica (a validação exige que a unidade
            // escolhida converta para a base do item) e à porção da tabela nutricional.
            'unidades'        => $this->unidadesAtivas(),
            'temNutricional'  => $prato->temTabelaNutricional(),
        ]);
    }

    public function update(StorePratoRequest $request, int $id): RedirectResponse
    {
        Prato::findOrFail($id)->update($request->validated());

        return to_route('alimentacao.pratos.edit', $id)->with('success', 'Prato atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $prato = Prato::findOrFail($id);

        // A ficha técnica é parte do prato (cascade no banco): remove junto.
        $prato->itens()->delete();

        return $this->safeDelete($prato)
            ?? to_route('alimentacao.pratos.index')->with('success', 'Prato removido com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $pratos = $this->baseQuery($request)
            ->with(['itens.item:itm_id,itm_nome', 'itens.unidadeMedida:umd_id,umd_sigla', 'porcaoUnidadeMedida:umd_id,umd_sigla'])
            ->orderBy('prt_nome')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($pratos, $request)
            : $this->exportCsv($pratos);
    }

    private function itensDoPrato(Prato $prato)
    {
        return $prato->itens()
            ->with(['item:itm_id,itm_nome,itm_umd_id', 'item.unidadeMedida:umd_id,umd_sigla', 'unidadeMedida:umd_id,umd_sigla'])
            ->get()
            ->map(fn ($i) => [
                'pri_id'             => $i->pri_id,
                'pri_itm_id'         => $i->pri_itm_id,
                'pri_umd_id'         => $i->pri_umd_id,
                'pri_qtd_per_capita' => (float) $i->pri_qtd_per_capita,
                'item_nome'          => $i->item?->itm_nome,
                'umd_sigla'          => $i->unidadeMedida?->umd_sigla,
                'item_umd_sigla'     => $i->item?->unidadeMedida?->umd_sigla,
            ]);
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');

        return Prato::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('prt_fl_ativo', $situacao === 'ativo'));
    }

    private function exportCsv($pratos): StreamedResponse
    {
        $filename = 'pratos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($pratos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'Prato', 'Descrição', 'Composição (itens)', 'Ficha técnica', 'Porção',
                'Energia (kcal)', 'Carboidratos (g)', 'Açúcares (g)', 'Proteínas (g)',
                'Gorduras totais (g)', 'Gorduras saturadas (g)', 'Gorduras trans (g)',
                'Fibras (g)', 'Sódio (mg)', 'Situação',
            ], ';');

            $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

            foreach ($pratos as $p) {
                $composicao = $p->itens->map(fn ($i) => $i->item?->itm_nome)->filter()->implode(', ');

                $ficha = $p->itens
                    ->map(fn ($i) => $i->item?->itm_nome.' ('.rtrim(rtrim(number_format((float) $i->pri_qtd_per_capita, 4, ',', '.'), '0'), ',').' '.$i->unidadeMedida?->umd_sigla.'/aluno)')
                    ->implode(' | ');

                $porcao = $p->prt_nut_porcao
                    ? $num($p->prt_nut_porcao).' '.$p->porcaoUnidadeMedida?->umd_sigla
                    : '';

                fputcsv($out, [
                    $p->prt_nome,
                    $p->prt_descricao ?? '',
                    $composicao,
                    $ficha,
                    $porcao,
                    $num($p->prt_nut_energia_kcal),
                    $num($p->prt_nut_carboidrato_g),
                    $num($p->prt_nut_acucares_g),
                    $num($p->prt_nut_proteina_g),
                    $num($p->prt_nut_gordura_total_g),
                    $num($p->prt_nut_gordura_saturada_g),
                    $num($p->prt_nut_gordura_trans_g),
                    $num($p->prt_nut_fibra_g),
                    $num($p->prt_nut_sodio_mg),
                    $p->prt_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($pratos, Request $request): HttpResponse
    {
        $collection = collect($pratos);
        $filename   = 'pratos_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_pratos_pdf', [
            'pratos'        => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('prt_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('prt_fl_ativo', false)->count(),
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
