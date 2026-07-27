<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alimentacao\StoreFornecedorRequest;
use App\Models\Alimentacao\Fornecedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FornecedorController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alimentacao/fornecedores/Index', [
            'fornecedores' => $this->baseQuery($request)
                ->with(['municipio:mun_id,mun_nome,mun_uf'])
                ->orderBy('frn_razao_social')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => [
                'search'   => $request->input('search', ''),
                'tipo'     => $request->input('tipo', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/fornecedores/Create');
    }

    public function store(StoreFornecedorRequest $request): RedirectResponse
    {
        $fornecedor = Fornecedor::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('alimentacao.fornecedores.create')
                ->with('success', 'Fornecedor cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('alimentacao.fornecedores.edit', $fornecedor->frn_id)
            ->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    public function edit(int $id): Response
    {
        $fornecedor = Fornecedor::with(['municipio:mun_id,mun_nome,mun_uf', 'bairro:bai_id,bai_nome'])->findOrFail($id);

        return Inertia::render('alimentacao/fornecedores/Edit', [
            'fornecedor' => $fornecedor,
        ]);
    }

    public function update(StoreFornecedorRequest $request, int $id): RedirectResponse
    {
        Fornecedor::findOrFail($id)->update($request->validated());

        return to_route('alimentacao.fornecedores.edit', $id)->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->safeDelete(Fornecedor::findOrFail($id))
            ?? to_route('alimentacao.fornecedores.index')->with('success', 'Fornecedor removido com sucesso.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $fornecedores = $this->baseQuery($request)
            ->with(['municipio:mun_id,mun_nome,mun_uf'])
            ->orderBy('frn_razao_social')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($fornecedores, $request)
            : $this->exportCsv($fornecedores);
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');
        $tipo     = $request->input('tipo');

        return Fornecedor::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($tipo === 'agricultura_familiar', fn ($q) => $q->where('frn_fl_agricultura_familiar', true))
            ->when($tipo === 'pj', fn ($q) => $q->whereNotNull('frn_cnpj'))
            ->when($tipo === 'pf', fn ($q) => $q->whereNotNull('frn_cpf'))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('frn_fl_ativo', $situacao === 'ativo'));
    }

    private function exportCsv($fornecedores): StreamedResponse
    {
        $filename = 'fornecedores_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($fornecedores) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Documento', 'Razão Social', 'Nome Fantasia', 'Agric. Familiar', 'E-mail', 'Telefone', 'Município', 'Situação'], ';');
            foreach ($fornecedores as $f) {
                fputcsv($out, [
                    $this->documentoFormatado($f),
                    $f->frn_razao_social,
                    $f->frn_nome_fantasia ?? '',
                    $f->frn_fl_agricultura_familiar ? 'Sim' : 'Não',
                    $f->frn_email ?? '',
                    $this->foneFormatado($f->frn_fone),
                    $f->municipio ? $f->municipio->mun_nome.'/'.$f->municipio->mun_uf : '',
                    $f->frn_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($fornecedores, Request $request): HttpResponse
    {
        $collection = collect($fornecedores);
        $filename   = 'fornecedores_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_fornecedores_pdf', [
            'fornecedores'  => $collection,
            'documento'     => fn ($f) => $this->documentoFormatado($f),
            'fone'          => fn ($f) => $this->foneFormatado($f->frn_fone),
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('frn_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('frn_fl_ativo', false)->count(),
            'totalAgric'    => $collection->where('frn_fl_agricultura_familiar', true)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** Fixo (10) → (11) 3456-7890 · Celular (11) → (11) 98765-4321. */
    private function foneFormatado(?string $fone): string
    {
        $d = preg_replace('/\D/', '', (string) $fone);

        return match (strlen($d)) {
            11      => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $d),
            10      => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $d),
            default => (string) $fone,
        };
    }

    /**
     * CNPJ 00.000.000/0000-00 · CPF 000.000.000-00 (guardados sem máscara).
     *
     * O CNPJ alfanumérico (jul/2026) tem [0-9A-Z] nas 12 primeiras posições e
     * só o DV numérico — casar com \d deixaria esses CNPJs crus no relatório.
     */
    private function documentoFormatado(Fornecedor $f): string
    {
        if ($f->frn_cnpj) {
            return preg_replace('/^([0-9A-Z]{2})([0-9A-Z]{3})([0-9A-Z]{3})([0-9A-Z]{4})(\d{2})$/', '$1.$2.$3/$4-$5', $f->frn_cnpj);
        }

        if ($f->frn_cpf) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $f->frn_cpf);
        }

        return '';
    }
}
