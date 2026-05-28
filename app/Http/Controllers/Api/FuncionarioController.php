<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuncionarioController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        $query = Funcionario::query()
            ->orderBy('fun_nome');

        if ($term) {
            // Remove máscara do CPF para comparação pura de dígitos
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function ($q) use ($term, $digits) {
                $q->where('fun_nome', 'ilike', "%{$term}%");

                if ($digits !== '') {
                    $q->orWhereRaw("regexp_replace(fun_cpf, '\D', '', 'g') ilike ?", ["%{$digits}%"]);
                }
            });
        }

        return response()->json(
            $query->limit(30)->get(['fun_id', 'fun_nome', 'fun_cpf'])
        );
    }
}
