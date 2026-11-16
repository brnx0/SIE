<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreCursoFormacaoSuperiorRequest;
use App\Models\Funcionario\CursoFormacaoSuperior;
use App\Models\Funcionario\NivelGraduacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de Curso de Formação Superior (catálogo INEP usado na escolaridade
 * de professores/funcionários). Cadastro mestre → inativação via cfs_fl_ativo;
 * exclusão só quando não houver vínculo (FK RESTRICT + safeDelete).
 */
class CursoFormacaoSuperiorController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $cursos = $this->baseQuery($request)
            ->with('nivel:ngr_id,ngr_nome')
            ->orderBy('cfs_nome')
            ->orderBy('cfs_codigo')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('cursos-formacao-superior/Index', [
            'cursos'  => $cursos,
            'niveis'  => NivelGraduacao::orderBy('ngr_nome')->get(['ngr_id', 'ngr_nome']),
            'filters' => [
                'search'   => $request->input('search', ''),
                'ngr_id'   => $request->input('ngr_id', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $cursos = $this->baseQuery($request)
            ->with('nivel:ngr_id,ngr_nome')
            ->orderBy('cfs_nome')
            ->orderBy('cfs_codigo')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($cursos, $request)
            : $this->exportCsv($cursos);
    }

    public function create(): Response
    {
        return Inertia::render('cursos-formacao-superior/Create', [
            'niveis' => $this->niveis(),
            'areas'  => $this->areas(),
        ]);
    }

    public function store(StoreCursoFormacaoSuperiorRequest $request): RedirectResponse
    {
        $curso = CursoFormacaoSuperior::create($this->dados($request));

        if ($request->boolean('continue_new')) {
            return to_route('cursos-formacao-superior.create')->with('success', 'Curso cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('cursos-formacao-superior.edit', $curso)->with('success', 'Curso de formação superior cadastrado com sucesso.');
    }

    public function edit(CursoFormacaoSuperior $curso): Response
    {
        return Inertia::render('cursos-formacao-superior/Edit', [
            'curso'           => $curso,
            'niveis'          => $this->niveis($curso->cfs_ngr_id),
            'areas'           => $this->areas(),
            'totalVinculos'   => DB::table('edu_funcionario_escolaridade')->where('fes_cfs_id', $curso->cfs_id)->count(),
        ]);
    }

    public function update(StoreCursoFormacaoSuperiorRequest $request, CursoFormacaoSuperior $curso): RedirectResponse
    {
        // Os vínculos (edu_funcionario_escolaridade.fes_cfs_id) apontam para o
        // cfs_id, que não muda — editar código/nome preserva as formações.
        $curso->update($this->dados($request));

        return to_route('cursos-formacao-superior.edit', $curso)->with('success', 'Curso de formação superior atualizado com sucesso.');
    }

    public function destroy(CursoFormacaoSuperior $curso): RedirectResponse
    {
        return $this->safeDelete($curso)
            ?? to_route('cursos-formacao-superior.index')->with('success', 'Curso removido com sucesso.');
    }

    /** Campos validados + derivados (grau texto e área geral). */
    private function dados(StoreCursoFormacaoSuperiorRequest $request): array
    {
        $dados = $request->safe()->only([
            'cfs_cd_area', 'cfs_area_detalhada', 'cfs_codigo', 'cfs_nome', 'cfs_ngr_id', 'cfs_fl_ativo',
        ]);

        // cfs_grau (texto) segue espelhando o nível — usado no lookup da escolaridade.
        $dados['cfs_grau'] = NivelGraduacao::whereKey($dados['cfs_ngr_id'])->value('ngr_nome');

        // Área geral (2 primeiros dígitos) herdada de outro curso da mesma área geral.
        $dados['cfs_area_geral'] = CursoFormacaoSuperior::where('cfs_codigo', 'like', substr($dados['cfs_cd_area'], 0, 2) . '%')
            ->whereNotNull('cfs_area_geral')
            ->value('cfs_area_geral');

        return $dados;
    }

    /** Níveis ativos + o salvo no curso (mesmo se inativado depois). */
    private function niveis(?int $incluirId = null)
    {
        $query = NivelGraduacao::query()->orderBy('ngr_nome');
        $this->filtroAtivoOuIncluso($query, 'ngr_fl_ativo', 'ngr_id', $incluirId ? [$incluirId] : []);

        return $query->get(['ngr_id', 'ngr_nome', 'ngr_fl_ativo']);
    }

    /** Áreas já conhecidas (código → nome), para autopreencher o nome da área. */
    private function areas()
    {
        return DB::table('edu_curso_formacao_superior')
            ->whereNotNull('cfs_cd_area')
            ->selectRaw('cfs_cd_area AS codigo, MIN(cfs_area_detalhada) AS nome')
            ->groupBy('cfs_cd_area')
            ->orderBy('cfs_cd_area')
            ->get();
    }

    private function baseQuery(Request $request)
    {
        return CursoFormacaoSuperior::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('ngr_id'), fn ($q, $id) => $q->where('cfs_ngr_id', (int) $id));
    }

    private function exportCsv($cursos): StreamedResponse
    {
        $filename = 'cursos_formacao_superior_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($cursos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Cód. Área', 'Área', 'Cód. Curso', 'Curso', 'Nível de Graduação', 'Situação'], ';');
            foreach ($cursos as $c) {
                fputcsv($out, [
                    $c->cfs_cd_area ?? '',
                    $c->cfs_area_detalhada ?? '',
                    $c->cfs_codigo,
                    $c->cfs_nome,
                    $c->nivel?->ngr_nome ?? $c->cfs_grau ?? '',
                    $c->cfs_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($cursos, Request $request): HttpResponse
    {
        $collection = collect($cursos);
        $filename   = 'cursos_formacao_superior_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.cursos_formacao_superior_pdf', [
            'cursos'        => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('cfs_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('cfs_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
            'nivelFiltro'   => $request->input('ngr_id') ? NivelGraduacao::find($request->input('ngr_id'))?->ngr_nome : '',
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
