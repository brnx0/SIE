<?php

namespace App\Http\Controllers\Censo;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Services\Censo\CensoExportService;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CensoExportController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $anoExercicio = AnoLetivo::where('anl_fl_em_exercicio', true)->first(['anl_id', 'anl_ano']);

        return Inertia::render('censo/Exportacao/Form', [
            'anoExercicio' => $anoExercicio,
            'escolas'      => UserAccess::escolasVisiveis($user),
            'userEscola'   => UserAccess::escolaDefault($user),
            'isAdmin'      => $user->isAdmin(),
        ]);
    }

    public function download(Request $request, CensoExportService $service): StreamedResponse
    {
        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $ano = AnoLetivo::where('anl_fl_em_exercicio', true)->firstOrFail();
        $escola = Escola::findOrFail($data['esc_id']);

        $conteudo = $service->gerar($escola, $ano);
        // Educacenso exige ISO-8859-1.
        $iso = mb_convert_encoding($conteudo, 'ISO-8859-1', 'UTF-8');

        $nome = 'censo_'.$ano->anl_ano.'_'.($escola->esc_cd_inep ?: $escola->esc_id).'.txt';

        return response()->streamDownload(function () use ($iso) {
            echo $iso;
        }, $nome, [
            'Content-Type' => 'text/plain; charset=ISO-8859-1',
        ]);
    }
}
