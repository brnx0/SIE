<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base dos catálogos simples da alimentação escolar — os cadastros que têm
 * apenas nome + situação (categoria de alimento, tipo de item, marca, tipo de
 * retirada). Todos compartilham a mesma listagem, o mesmo formulário e o mesmo
 * export; só mudam o model e os rótulos.
 *
 * Um CRUD novo aqui = uma subclasse com 3 métodos e as rotas.
 */
abstract class CatalogoSimplesController extends Controller
{
    /** FQCN do model. */
    abstract protected function model(): string;

    /** Prefixo de 3 letras das colunas (ex.: 'cat' → cat_id, cat_nome, cat_fl_ativo). */
    abstract protected function prefixo(): string;

    /**
     * Rótulos e rota do recurso.
     *
     * @return array{rota:string, singular:string, plural:string, artigo:string, descricao:string}
     */
    abstract protected function config(): array;

    private function coluna(string $sufixo): string
    {
        return $this->prefixo().'_'.$sufixo;
    }

    private function tabela(): string
    {
        return (new ($this->model())())->getTable();
    }

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $registros = $this->baseQuery($request)
            ->orderBy($this->coluna('nome'))
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('alimentacao/catalogos/Index', [
            'registros' => $registros,
            'config'    => $this->configParaTela(),
            'filters'   => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/catalogos/Create', [
            'config' => $this->configParaTela(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        $cfg   = $this->config();

        $this->model()::create($dados);

        if ($request->boolean('continue_new')) {
            return to_route($cfg['rota'].'.create')
                ->with('success', "{$cfg['singular']} cadastrad{$cfg['artigo']}. Pronto para o próximo cadastro.");
        }

        return to_route($cfg['rota'].'.index')
            ->with('success', "{$cfg['singular']} cadastrad{$cfg['artigo']} com sucesso.");
    }

    public function edit(int $id): Response
    {
        return Inertia::render('alimentacao/catalogos/Edit', [
            'registro' => $this->model()::findOrFail($id),
            'config'   => $this->configParaTela(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $registro = $this->model()::findOrFail($id);
        $registro->update($this->validar($request, $id));

        $cfg = $this->config();

        return to_route($cfg['rota'].'.edit', $id)
            ->with('success', "{$cfg['singular']} atualizad{$cfg['artigo']} com sucesso.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $registro = $this->model()::findOrFail($id);
        $cfg      = $this->config();

        return $this->safeDelete($registro)
            ?? to_route($cfg['rota'].'.index')
                ->with('success', "{$cfg['singular']} removid{$cfg['artigo']} com sucesso.");
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $registros = $this->baseQuery($request)->orderBy($this->coluna('nome'))->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($registros, $request)
            : $this->exportCsv($registros);
    }

    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $nome  = $this->coluna('nome');
        $ativo = $this->coluna('fl_ativo');

        $request->merge([
            $nome  => mb_strtoupper(trim((string) $request->input($nome, '')), 'UTF-8'),
            $ativo => $request->boolean($ativo),
        ]);

        $cfg = $this->config();

        return $request->validate(
            [
                $nome => [
                    'required', 'string', 'max:80',
                    Rule::unique($this->tabela(), $nome)->ignore($ignorarId, $this->coluna('id')),
                ],
                $ativo => ['boolean'],
            ],
            [],
            [
                $nome  => mb_strtolower($cfg['singular'], 'UTF-8'),
                $ativo => 'situação',
            ],
        );
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');

        return $this->model()::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where($this->coluna('fl_ativo'), $situacao === 'ativo'));
    }

    /** Config + nomes de coluna, para a tela genérica saber o que ler de cada registro. */
    private function configParaTela(): array
    {
        return $this->config() + [
            'col_id'    => $this->coluna('id'),
            'col_nome'  => $this->coluna('nome'),
            'col_ativo' => $this->coluna('fl_ativo'),
        ];
    }

    private function exportCsv($registros): StreamedResponse
    {
        $cfg      = $this->config();
        $nome     = $this->coluna('nome');
        $ativo    = $this->coluna('fl_ativo');
        $filename = str_replace('/', '_', $cfg['rota']).'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($registros, $nome, $ativo, $cfg) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [$cfg['singular'], 'Situação'], ';');
            foreach ($registros as $r) {
                fputcsv($out, [$r->{$nome}, $r->{$ativo} ? 'Ativo' : 'Inativo'], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($registros, Request $request): HttpResponse
    {
        $cfg        = $this->config();
        $collection = collect($registros);
        $ativo      = $this->coluna('fl_ativo');
        $filename   = str_replace('/', '_', $cfg['rota']).'_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_catalogo_pdf', [
            'registros'     => $collection,
            'titulo'        => $cfg['plural'],
            'colNome'       => $this->coluna('nome'),
            'colAtivo'      => $ativo,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where($ativo, true)->count(),
            'totalInativos' => $collection->where($ativo, false)->count(),
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
