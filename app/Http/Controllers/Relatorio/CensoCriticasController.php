<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Services\Censo\CensoCriticasService;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CensoCriticasController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $anoExercicio = AnoLetivo::where('anl_fl_em_exercicio', true)->first(['anl_id', 'anl_ano']);

        return Inertia::render('relatorios/CensoCriticas/Form', [
            'anoExercicio' => $anoExercicio,
            'escolas'      => UserAccess::escolasVisiveis($user),
            'userEscola'   => UserAccess::escolaDefault($user),
            'isAdmin'      => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request, CensoCriticasService $service): Response
    {
        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $ano = AnoLetivo::where('anl_fl_em_exercicio', true)->firstOrFail();
        $escola = Escola::findOrFail($data['esc_id']);

        $resultado = $service->gerar($escola, $ano);

        return Inertia::render('relatorios/CensoCriticas/Resultado', [
            'filtros' => [
                'anl_id'   => $ano->anl_id,
                'anl_ano'  => $ano->anl_ano,
                'esc_id'   => $escola->esc_id,
                'esc_nome' => $escola->esc_nome,
            ],
            'criticas'   => $resultado['criticas'],
            'resumo'     => $resultado['resumo'],
            'pendencias' => $resultado['pendencias'],
            'total'      => count($resultado['criticas']),
        ]);
    }
}
