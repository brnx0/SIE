<?php

namespace App\Http\Controllers\Api\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Contrato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    /**
     * Lookup de contratos (empenho, ordem de fornecimento).
     *
     * Por padrão oferece só contrato VIGENTE — os demais não aceitam nova ordem.
     * `incluir_ids` mantém visível o contrato já vinculado ao registro em edição,
     * mesmo que ele tenha sido encerrado depois.
     */
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Contrato::query()
            ->with('fornecedor:frn_id,frn_razao_social')
            ->when($request->input('q'), fn ($q, $s) => $q->search($s))
            ->where(function ($q) use ($incluirIds) {
                $q->where('ctr_situacao', Contrato::SITUACAO_VIGENTE);
                if (! empty($incluirIds)) {
                    $q->orWhereIn('ctr_id', $incluirIds);
                }
            })
            ->orderByDesc('ctr_ano')
            ->orderBy('ctr_nr_contrato');

        return response()->json(
            $query->limit(50)->get()->map(fn ($c) => [
                'ctr_id'          => $c->ctr_id,
                'ctr_nr_contrato' => $c->ctr_nr_contrato,
                'ctr_ano'         => $c->ctr_ano,
                'ctr_situacao'    => $c->ctr_situacao,
                'fornecedor_nome' => $c->fornecedor?->frn_razao_social,
                // Rótulo pronto: o combobox mostra contrato + fornecedor numa linha só.
                'rotulo'          => "{$c->ctr_nr_contrato}/{$c->ctr_ano} — ".($c->fornecedor?->frn_razao_social ?? 'sem fornecedor'),
            ]),
        );
    }
}
