<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Support\CargoTransporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookups de pessoal do transporte escolar.
 *
 * Motorista e monitor não são cadastros próprios: são funcionários da rede
 * com lotação ativa no cargo correspondente (CargoTransporte). Segue o padrão
 * de lookup do projeto — incluir_ids + filtroAtivoOuIncluso — para que um
 * funcionário inativado depois continue visível ao editar a rota/diário.
 */
class FuncionarioTransporteController extends Controller
{
    public function motoristas(Request $request): JsonResponse
    {
        return $this->buscar($request, 'motorista');
    }

    public function monitores(Request $request): JsonResponse
    {
        return $this->buscar($request, 'monitor');
    }

    private function buscar(Request $request, string $papel): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $incluirIds = $this->incluirIds($request);
        $cargoIds   = CargoTransporte::ids($papel);

        $query = Funcionario::query()->orderBy('fun_nome');

        // Restringe ao cargo do papel — mas nunca esconde um vínculo já salvo,
        // que pode ter sido lotado em outro cargo depois de entrar na rota.
        $query->where(function ($w) use ($cargoIds, $incluirIds) {
            $w->whereHas('admissoes.lotacoes', fn ($l) => $l
                ->where('lot_fl_ativo', true)
                ->whereIn('lot_crg_id', $cargoIds));

            if (! empty($incluirIds)) {
                $w->orWhereIn('fun_id', $incluirIds);
            }
        });

        $this->filtroAtivoOuIncluso($query, 'fun_fl_ativo', 'fun_id', $incluirIds);

        if ($term) {
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function ($q) use ($term, $digits) {
                $q->where('fun_nome', 'ilike', "%{$term}%");

                if ($digits !== '') {
                    $q->orWhereRaw("regexp_replace(fun_cpf, '\D', '', 'g') ilike ?", ["%{$digits}%"]);
                }
            });
        }

        return response()->json(
            $query->limit(50)->get([
                'fun_id',
                'fun_nome',
                'fun_cpf',
                'fun_cnh_categoria',
                'fun_cnh_dt_validade',
                'fun_fl_ativo',
            ])
        );
    }
}
