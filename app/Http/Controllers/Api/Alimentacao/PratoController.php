<?php

namespace App\Http\Controllers\Api\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Prato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PratoController extends Controller
{
    /**
     * Lookup de pratos (cardápio, controle diário da preparação).
     * `incluir_ids` mantém visível o prato já vinculado ao registro em edição,
     * mesmo se ele tiver sido inativado depois.
     */
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Prato::query()
            ->withCount('itens')
            ->when($request->input('q'), fn ($q, $s) => $q->search($s))
            ->orderBy('prt_nome');

        $this->filtroAtivoOuIncluso($query, 'prt_fl_ativo', 'prt_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get()->map(fn ($p) => [
                'prt_id'       => $p->prt_id,
                'prt_nome'     => $p->prt_nome,
                'prt_fl_ativo' => $p->prt_fl_ativo,
                // Prato sem ficha técnica não calcula demanda nem baixa estoque —
                // a tela do cardápio avisa quem estiver montando.
                'itens_count'  => $p->itens_count,
            ]),
        );
    }
}
