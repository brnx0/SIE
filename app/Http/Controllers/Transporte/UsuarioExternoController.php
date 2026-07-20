<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreUsuarioExternoRequest;
use App\Models\Transporte\UsuarioExterno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsuarioExternoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $usuarios = $this->baseQuery($request)
            ->with('instituicao:tin_id,tin_nome')
            ->orderBy('tue_nome')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/usuarios/Index', [
            'usuarios' => $usuarios,
            'filters'  => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $usuarios = $this->baseQuery($request)
            ->with('instituicao:tin_id,tin_nome')
            ->orderBy('tue_nome')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($usuarios, $request);
        }

        return $this->exportCsv($usuarios);
    }

    public function create(): Response
    {
        return Inertia::render('transporte/usuarios/Create');
    }

    public function store(StoreUsuarioExternoRequest $request): RedirectResponse
    {
        $usuario = UsuarioExterno::create($request->validated());

        if ($request->boolean('continue_new')) {
            return to_route('transporte.usuarios.create')->with('success', 'Usuário do transporte cadastrado. Pronto para o próximo cadastro.');
        }

        return to_route('transporte.usuarios.edit', $usuario)->with('success', 'Usuário do transporte cadastrado com sucesso.');
    }

    public function edit(UsuarioExterno $usuario): Response
    {
        $usuario->load('instituicao:tin_id,tin_nome');

        return Inertia::render('transporte/usuarios/Edit', [
            'usuario' => $usuario,
        ]);
    }

    public function update(StoreUsuarioExternoRequest $request, UsuarioExterno $usuario): RedirectResponse
    {
        $usuario->update($request->validated());

        return to_route('transporte.usuarios.edit', $usuario)->with('success', 'Usuário do transporte atualizado com sucesso.');
    }

    public function destroy(UsuarioExterno $usuario): RedirectResponse
    {
        return $this->safeDelete($usuario)
            ?? to_route('transporte.usuarios.index')->with('success', 'Usuário do transporte removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        return UsuarioExterno::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s));
    }

    private function exportCsv($usuarios): StreamedResponse
    {
        $filename = 'transporte_usuarios_' . now()->format('Ymd_His') . '.csv';
        $fmt      = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return response()->streamDownload(function () use ($usuarios, $fmt) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'CPF', 'Nascimento', 'Telefone', 'Instituição', 'Justificativa', 'Situação'], ';');
            foreach ($usuarios as $u) {
                fputcsv($out, [
                    $u->tue_nome,
                    $u->tue_cpf ?? '',
                    $fmt($u->tue_dt_nascimento),
                    $u->tue_telefone ?? '',
                    $u->instituicao?->tin_nome ?? '',
                    $u->tue_justificativa,
                    $u->tue_fl_ativo ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($usuarios, Request $request): HttpResponse
    {
        $collection = collect($usuarios);
        $filename   = 'transporte_usuarios_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_usuarios_pdf', [
            'usuarios'      => $collection,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('tue_fl_ativo', true)->count(),
            'totalInativos' => $collection->where('tue_fl_ativo', false)->count(),
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
