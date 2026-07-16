<?php

namespace App\Http\Controllers\Api\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Lookup de itens (prato, cardápio, contratos, estoque).
     * `incluir_ids` mantém visível o item já vinculado ao registro em edição,
     * mesmo se ele tiver sido inativado depois.
     */
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Item::query()
            ->with('unidadeMedida:umd_id,umd_sigla')
            ->when($request->input('q'), fn ($q, $s) => $q->search($s))
            ->when($request->input('cat_id'), fn ($q, $id) => $q->where('itm_cat_id', (int) $id))
            ->when($request->boolean('agricultura_familiar'), fn ($q) => $q->where('itm_fl_agricultura_familiar', true))
            ->orderBy('itm_nome');

        $this->filtroAtivoOuIncluso($query, 'itm_fl_ativo', 'itm_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get()->map(fn ($i) => [
                'itm_id'       => $i->itm_id,
                'itm_nome'     => $i->itm_nome,
                'itm_cd_item'  => $i->itm_cd_item,
                'itm_umd_id'   => $i->itm_umd_id,
                'umd_sigla'    => $i->unidadeMedida?->umd_sigla,
                'itm_fl_ativo' => $i->itm_fl_ativo,
            ]),
        );
    }
}
