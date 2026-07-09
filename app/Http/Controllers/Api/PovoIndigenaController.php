<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PovoIndigena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PovoIndigenaController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        $items = PovoIndigena::query()
            ->search($term ?: null)
            ->limit(30)
            ->get(['pin_id', 'pin_cd_censo', 'pin_nome']);

        return response()->json($items);
    }
}
