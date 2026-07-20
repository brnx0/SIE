<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Services\Diario\ContextoDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contexto "minhas turmas de AEE" para o app mobile. Somente leitura.
 *
 * Espelha o ContextoController do diário regular, mas a turma AEE não tem série
 * nem disciplina: o que identifica o contexto é a SALA, o horário e os dias da
 * semana de atendimento. Por isso não existe endpoint de `disciplinas` aqui —
 * o AEE simplesmente não tem esse eixo.
 *
 * Autorização: rota gateada por `app.access:professor_aee` — ver routes/api.php.
 */
class AeeContextoController extends Controller
{
    public function __construct(private readonly ContextoDiarioService $contexto)
    {
    }

    /** GET api/v1/diario-aee/contexto/anos-letivos */
    public function anosLetivos(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->anosLetivosAee($request->user())
        );
    }

    /** GET api/v1/diario-aee/contexto/escolas?anl_id= */
    public function escolas(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->escolasAee($request->user(), (int) $request->input('anl_id'))
        );
    }

    /** GET api/v1/diario-aee/contexto/turmas?anl_id=&esc_id= */
    public function turmas(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->turmasAee(
                $request->user(),
                (int) $request->input('anl_id'),
                (int) $request->input('esc_id'),
            )
        );
    }

    /**
     * GET api/v1/diario-aee/contexto/unidades?anl_id=
     *
     * As unidades são do ano letivo, não da modalidade — mesma fonte do regular.
     */
    public function unidades(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->unidades((int) $request->input('anl_id'))
        );
    }
}
