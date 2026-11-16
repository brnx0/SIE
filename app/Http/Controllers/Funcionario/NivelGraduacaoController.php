<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreNivelGraduacaoRequest;
use App\Models\Funcionario\NivelGraduacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de Nível de Graduação Acadêmica — alimenta a lista suspensa do
 * Cadastro de Curso de Formação Superior.
 */
class NivelGraduacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $niveis = $this->baseQuery($request)
            ->withCount('cursos')
            ->orderBy('ngr_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('niveis-graduacao/Index', [
            'niveis'  => $niveis,
            'filters' => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $niveis = $this->baseQuery($request)->withCount('cursos')->orderBy('ngr_nome')->get();

        if ($request->input('format') === 'pdf') {
            $filename = 'niveis_graduacao_' . now()->format('Ymd_His') . '.pdf';
            $html = view('exports.niveis_graduacao_pdf', [
                'niveis'        => $niveis,
                'total'         => $niveis->count(),
                'totalAtivos'   => $niveis->where('ngr_fl_ativo', true)->count(),
                'totalInativos' => $niveis->where('ngr_fl_ativo', false)->count(),
                'search'        => $request->input('search', ''),
            ])->render();

            $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
            $mpdf->WriteHTML($html);

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $filename = 'niveis_graduacao_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($niveis) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nível de Graduação', 'Cursos', 'Situação'], ';');
            foreach ($niveis as $n) {
                fputcsv($out, [$n->ngr_nome, $n->cursos_count, $n->ngr_fl_ativo ? 'Ativo' : 'Inativo'], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): Response
    {
        return Inertia::render('niveis-graduacao/Create');
    }

    public function store(StoreNivelGraduacaoRequest $request): RedirectResponse
    {
        $nivel = NivelGraduacao::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('niveis-graduacao.create')->with('success', 'Nível cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('niveis-graduacao.edit', $nivel)->with('success', 'Nível de graduação cadastrado com sucesso.');
    }

    public function edit(NivelGraduacao $nivel): Response
    {
        return Inertia::render('niveis-graduacao/Edit', [
            'nivel' => $nivel,
        ]);
    }

    public function update(StoreNivelGraduacaoRequest $request, NivelGraduacao $nivel): RedirectResponse
    {
        $nivel->update($request->validated());

        // Mantém o texto espelhado nos cursos (usado no lookup da escolaridade).
        $nivel->cursos()->update(['cfs_grau' => $nivel->ngr_nome]);

        return to_route('niveis-graduacao.edit', $nivel)->with('success', 'Nível de graduação atualizado com sucesso.');
    }

    public function destroy(NivelGraduacao $nivel): RedirectResponse
    {
        return $this->safeDelete($nivel)
            ?? to_route('niveis-graduacao.index')->with('success', 'Nível de graduação removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return NivelGraduacao::query()
            ->when($request->input('search'), fn ($q, $s) => $q->where('ngr_nome', 'ilike', "%{$s}%"));
    }
}
