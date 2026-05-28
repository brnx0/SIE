<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreFuncionarioRequest;
use App\Http\Requests\Funcionario\UpdateFuncionarioRequest;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\ParametroEntidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuncionarioController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('funcionarios/Index', [
            'funcionarios' => $this->baseQuery($request)->paginate($perPage)->withQueryString(),
            'filters'      => [
                'search'   => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $funcionarios = $this->baseQuery($request)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($funcionarios, $request);
        }

        return $this->exportCsv($funcionarios);
    }

    public function create(): Response
    {
        return Inertia::render('funcionarios/Create');
    }

    public function store(StoreFuncionarioRequest $request): RedirectResponse
    {
        $this->checkHomonimo($request);

        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['fun_foto']);

            if ($request->hasFile('fun_foto')) {
                $data['fun_foto'] = $request->file('fun_foto')->store('funcionarios', 'public');
            }

            Funcionario::create($data);
        });

        return to_route('funcionarios.index')->with('success', 'Funcionário cadastrado com sucesso.');
    }

    public function edit(Funcionario $funcionario): Response
    {
        $funcionario->load([
            'municipioNascimento:mun_id,mun_nome,mun_uf',
            'municipioCertidao:mun_id,mun_nome,mun_uf',
            'admissoes.cargo:crg_id,crg_nome',
            'admissoes.lotacoes.escola:esc_id,esc_nome',
            'admissoes.lotacoes.cargo:crg_id,crg_nome',
        ]);

        return Inertia::render('funcionarios/Edit', [
            'funcionario' => $funcionario,
        ]);
    }

    public function update(UpdateFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $this->checkHomonimo($request, $funcionario->fun_id);

        DB::transaction(function () use ($request, $funcionario) {
            $data = $request->safe()->except(['fun_foto']);

            if ($request->hasFile('fun_foto')) {
                if ($funcionario->fun_foto) {
                    Storage::disk('public')->delete($funcionario->fun_foto);
                }
                $data['fun_foto'] = $request->file('fun_foto')->store('funcionarios', 'public');
            }

            $funcionario->update($data);
        });

        return to_route('funcionarios.index')->with('success', 'Funcionário atualizado com sucesso.');
    }

    protected function checkHomonimo(Request $request, ?int $excludeId = null): void
    {
        $params = ParametroEntidade::firstOrFail();

        if (! $params->par_fl_alertar_homonimos) return;
        if ($request->boolean('confirm_homonimo')) return;

        $nome   = $request->input('fun_nome');
        $dtNasc = $request->input('fun_dt_nascimento');

        if (! $nome || ! $dtNasc) return;

        $matches = Funcionario::query()
            ->whereRaw('unaccent(upper(fun_nome)) = unaccent(upper(?))', [$nome])
            ->whereDate('fun_dt_nascimento', $dtNasc)
            ->when($excludeId, fn ($q) => $q->where('fun_id', '!=', $excludeId))
            ->orderBy('fun_id')
            ->limit(20)
            ->get(['fun_id', 'fun_nome', 'fun_dt_nascimento', 'fun_cpf']);

        if ($matches->isEmpty()) return;

        $payload = $matches->map(fn ($f) => [
            'fun_id'            => $f->fun_id,
            'fun_nome'          => $f->fun_nome,
            'fun_dt_nascimento' => $f->fun_dt_nascimento?->format('Y-m-d'),
            'fun_cpf'           => $f->fun_cpf,
        ])->all();

        throw ValidationException::withMessages([
            'homonimo' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function destroy(Funcionario $funcionario): RedirectResponse
    {
        if ($funcionario->fun_foto) {
            Storage::disk('public')->delete($funcionario->fun_foto);
        }

        return $this->safeDelete($funcionario)
            ?? to_route('funcionarios.index')->with('success', 'Funcionário removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        $search = $request->string('search')->toString();

        return Funcionario::query()
            ->with('municipioNascimento:mun_id,mun_nome,mun_uf')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('fun_nome', 'ilike', "%{$search}%")
                        ->orWhere('fun_cpf', 'like', "%{$search}%");
                });
            })
            ->orderBy('fun_nome');
    }

    private function exportCsv($funcionarios): StreamedResponse
    {
        $filename = 'funcionarios_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($funcionarios) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'CPF', 'Município de Origem', 'UF', 'Status'], ';');
            foreach ($funcionarios as $f) {
                $cpf = $f->fun_cpf
                    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $f->fun_cpf)
                    : '';
                fputcsv($out, [
                    $f->fun_nome,
                    $cpf,
                    $f->municipioNascimento?->mun_nome ?? '',
                    $f->municipioNascimento?->mun_uf ?? '',
                    $f->fun_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($funcionarios, Request $request): HttpResponse
    {
        $collection = collect($funcionarios);
        $filename   = 'funcionarios_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.funcionarios_pdf', [
            'funcionarios'  => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('fun_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('fun_fl_ativo', false)->count(),
            'search'        => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
