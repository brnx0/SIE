<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreInstituicaoRequest;
use App\Models\Transporte\Instituicao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstituicaoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $instituicoes = $this->baseQuery($request)
            ->with(['escola:esc_id,esc_nome', 'bairro:bai_id,bai_nome'])
            ->orderBy('tin_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/instituicoes/Index', [
            'instituicoes' => $instituicoes,
            'esferas'      => Instituicao::ESFERAS,
            'filters'      => [
                'search'   => $request->input('search', ''),
                'esfera'   => $request->input('esfera', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $instituicoes = $this->baseQuery($request)
            ->with(['escola:esc_id,esc_nome', 'bairro:bai_id,bai_nome'])
            ->orderBy('tin_nome')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($instituicoes, $request);
        }

        return $this->exportCsv($instituicoes);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/instituicoes/Create', [
            'esferas' => Instituicao::ESFERAS,
        ]);
    }

    public function store(StoreInstituicaoRequest $request): RedirectResponse
    {
        $instituicao = Instituicao::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('transporte.instituicoes.create')->with('success', 'Instituição cadastrada. Pronto para o próximo cadastro.');
        }

        return to_route('transporte.instituicoes.edit', $instituicao)->with('success', 'Instituição cadastrada com sucesso.');
    }

    public function edit(Instituicao $instituicao): Response
    {
        $instituicao->load(['escola:esc_id,esc_nome', 'bairro:bai_id,bai_nome', 'municipio']);

        return Inertia::render('transporte/instituicoes/Edit', [
            'instituicao' => $instituicao,
            'esferas'     => Instituicao::ESFERAS,
        ]);
    }

    public function update(StoreInstituicaoRequest $request, Instituicao $instituicao): RedirectResponse
    {
        $instituicao->update($request->validated());

        return to_route('transporte.instituicoes.edit', $instituicao)->with('success', 'Instituição atualizada com sucesso.');
    }

    public function destroy(Instituicao $instituicao): RedirectResponse
    {
        return $this->safeDelete($instituicao)
            ?? to_route('transporte.instituicoes.index')->with('success', 'Instituição removida com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Instituicao::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('esfera'), fn ($q, $e) => $q->where('tin_esfera', $e));
    }

    private function exportCsv($instituicoes): StreamedResponse
    {
        $filename = 'transporte_instituicoes_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($instituicoes) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'Esfera', 'Escola da Rede', 'Endereço', 'Bairro', 'Telefone', 'Situação'], ';');
            foreach ($instituicoes as $i) {
                fputcsv($out, [
                    $i->tin_nome,
                    Instituicao::ESFERAS[$i->tin_esfera] ?? $i->tin_esfera,
                    $i->escola?->esc_nome ?? '',
                    $i->tin_endereco ?? '',
                    $i->bairro?->bai_nome ?? '',
                    $i->tin_telefone ?? '',
                    $i->tin_fl_ativo ? 'Ativa' : 'Inativa',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($instituicoes, Request $request): HttpResponse
    {
        $collection = collect($instituicoes);
        $filename   = 'transporte_instituicoes_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_instituicoes_pdf', [
            'instituicoes'  => $collection,
            'esferas'       => Instituicao::ESFERAS,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('tin_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('tin_fl_ativo', false)->count(),
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
