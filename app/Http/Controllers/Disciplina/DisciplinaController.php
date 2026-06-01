<?php

namespace App\Http\Controllers\Disciplina;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disciplina\StoreDisciplinaRequest;
use App\Models\Disciplina\AreaConhecimento;
use App\Models\Disciplina\Disciplina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisciplinaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $disciplinas = $this->baseQuery($request)
            ->with('areaConhecimento:arc_id,arc_nome')
            ->orderBy('arc_id')
            ->orderBy('dis_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('disciplinas/Index', [
            'disciplinas' => $disciplinas,
            'areas'       => AreaConhecimento::orderBy('arc_nome')->get(['arc_id', 'arc_nome']),
            'filters'     => [
                'search'   => $request->input('search', ''),
                'arc_id'   => $request->input('arc_id', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $disciplinas = $this->baseQuery($request)
            ->with('areaConhecimento:arc_id,arc_nome')
            ->orderBy('arc_id')
            ->orderBy('dis_nome')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($disciplinas, $request);
        }

        return $this->exportCsv($disciplinas);
    }

    public function create(): Response
    {
        return Inertia::render('disciplinas/Create', [
            'areas' => AreaConhecimento::orderBy('arc_nome')->get(['arc_id', 'arc_nome']),
        ]);
    }

    public function store(StoreDisciplinaRequest $request): RedirectResponse
    {
        $disciplina = Disciplina::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('disciplinas.create')->with('success', 'Disciplina cadastrada. Pronto para o próximo cadastro.');
        }

        return to_route('disciplinas.edit', $disciplina)->with('success', 'Disciplina cadastrada com sucesso.');
    }

    public function edit(Disciplina $disciplina): Response
    {
        return Inertia::render('disciplinas/Edit', [
            'disciplina' => $disciplina,
            'areas'      => AreaConhecimento::orderBy('arc_nome')->get(['arc_id', 'arc_nome']),
        ]);
    }

    public function update(StoreDisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $disciplina->update($request->validated());

        return to_route('disciplinas.edit', $disciplina)->with('success', 'Disciplina atualizada com sucesso.');
    }

    public function destroy(Disciplina $disciplina): RedirectResponse
    {
        return $this->safeDelete($disciplina)
            ?? to_route('disciplinas.index')->with('success', 'Disciplina removida com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return Disciplina::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('arc_id'), fn ($q, $id) => $q->where('arc_id', (int) $id));
    }

    private function exportCsv($disciplinas): StreamedResponse
    {
        $filename = 'disciplinas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($disciplinas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome Reduzido', 'Nome (MEC)', 'Área do Conhecimento', 'Sigla', 'Cód. Ref.', 'Situação'], ';');
            foreach ($disciplinas as $d) {
                fputcsv($out, [
                    $d->dis_nome,
                    $d->dis_nome_mec,
                    $d->areaConhecimento?->arc_nome ?? '',
                    $d->dis_sigla ?? '',
                    $d->dis_cod_ref ?? '',
                    $d->dis_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($disciplinas, Request $request): HttpResponse
    {
        $collection = collect($disciplinas);
        $filename   = 'disciplinas_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.disciplinas_pdf', [
            'disciplinas'   => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('dis_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('dis_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
            'areaFiltro'    => $request->input('arc_id') ? AreaConhecimento::find($request->input('arc_id'))?->arc_nome : '',
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
