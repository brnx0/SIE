<?php

namespace App\Http\Controllers\Api\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Fornecedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    /**
     * Lookup de fornecedores (contratos, entradas de estoque, agricultura familiar).
     * `incluir_ids` mantém visível o fornecedor já vinculado ao registro em edição,
     * mesmo se ele tiver sido inativado depois.
     */
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Fornecedor::query()
            ->when($request->input('q'), fn ($q, $s) => $q->search($s))
            ->when($request->boolean('agricultura_familiar'), fn ($q) => $q->where('frn_fl_agricultura_familiar', true))
            ->orderBy('frn_razao_social');

        $this->filtroAtivoOuIncluso($query, 'frn_fl_ativo', 'frn_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get()->map(fn ($f) => [
                'frn_id'                      => $f->frn_id,
                'frn_razao_social'            => $f->frn_razao_social,
                'frn_nome_fantasia'           => $f->frn_nome_fantasia,
                'frn_cnpj'                    => $f->frn_cnpj,
                'frn_cpf'                     => $f->frn_cpf,
                'frn_fl_agricultura_familiar' => $f->frn_fl_agricultura_familiar,
                'frn_fl_ativo'                => $f->frn_fl_ativo,
            ]),
        );
    }
}
