<?php

namespace App\Http\Controllers\Censo;

use App\Http\Controllers\Controller;
use App\Jobs\Censo\CommitCensoLoteJob;
use App\Jobs\Censo\StageCensoLoteJob;
use App\Models\Censo\CensoLote;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela de importação do Censo (migração INEP). Upload → parse (Job) → preview →
 * commit (Job). Ver docs/regras/importacao_censo.md.
 */
class CensoImportacaoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('censo-importacao/Index', [
            'lotes' => CensoLote::query()
                ->orderByDesc('icl_id')
                ->limit(30)
                ->get([
                    'icl_id', 'icl_arquivo_nome', 'icl_anl_id', 'icl_versao_layout',
                    'icl_status', 'icl_total_linhas', 'icl_total_erros', 'icl_contadores',
                    'icl_mensagem', 'icl_created_at',
                ]),
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'arquivo' => ['required', 'file', 'max:51200'], // até 50 MB
            'anl_id'  => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
        ]);

        $file = $request->file('arquivo');
        $stored = $file->storeAs('censo', now()->format('Ymd_His').'_'.$file->getClientOriginalName());

        $lote = CensoLote::create([
            'icl_arquivo_nome' => $file->getClientOriginalName(),
            'icl_arquivo_path' => Storage::path($stored),
            'icl_anl_id'       => $data['anl_id'],
            'icl_status'       => 'PENDENTE',
            'icl_created_by'   => $request->user()?->getAuthIdentifier(),
        ]);

        StageCensoLoteJob::dispatch((int) $lote->icl_id);

        return back()->with('success', 'Arquivo enviado. Processando em segundo plano — atualize para ver o resultado.');
    }

    public function commit(CensoLote $lote): RedirectResponse
    {
        if (! in_array($lote->icl_status, ['PARSEADO', 'COMMITADO'], true)) {
            return back()->with('error', 'Lote ainda não está pronto para importar.');
        }

        CommitCensoLoteJob::dispatch((int) $lote->icl_id);

        return back()->with('success', 'Importação iniciada em segundo plano.');
    }

    public function destroy(CensoLote $lote): RedirectResponse
    {
        if ($lote->icl_arquivo_path && Storage::exists('censo/'.basename($lote->icl_arquivo_path))) {
            Storage::delete('censo/'.basename($lote->icl_arquivo_path));
        }

        $lote->registros()->delete();
        $lote->delete();

        return back()->with('success', 'Lote removido.');
    }
}
