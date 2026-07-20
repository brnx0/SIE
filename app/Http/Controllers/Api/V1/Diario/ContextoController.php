<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Services\Diario\ContextoDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contexto "minhas turmas" do Diário de Classe (regular) para o app mobile.
 * Toda a regra vive no ContextoDiarioService (fonte única). Somente leitura.
 *
 * Autorização: rota gateada por `app.access` (EnsureAppAccess) — SOMENTE
 * professor/professor_aee, SEM bypass de admin — ver routes/api.php.
 */
class ContextoController extends Controller
{
    public function __construct(private readonly ContextoDiarioService $contexto)
    {
    }

    /** GET api/v1/diario/contexto/anos-letivos */
    public function anosLetivos(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->anosLetivos($request->user())
        );
    }

    /** GET api/v1/diario/contexto/escolas?anl_id= */
    public function escolas(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->escolas($request->user(), (int) $request->input('anl_id'))
        );
    }

    /** GET api/v1/diario/contexto/turmas?anl_id=&esc_id= */
    public function turmas(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->turmas(
                $request->user(),
                (int) $request->input('anl_id'),
                (int) $request->input('esc_id'),
            )
        );
    }

    /** GET api/v1/diario/contexto/disciplinas?tur_id= */
    public function disciplinas(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->disciplinas($request->user(), (int) $request->input('tur_id'))
        );
    }

    /** GET api/v1/diario/contexto/unidades?anl_id= */
    public function unidades(Request $request): JsonResponse
    {
        return response()->json(
            $this->contexto->unidades((int) $request->input('anl_id'))
        );
    }
}
