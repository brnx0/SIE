<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Diario\ContextoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — App mobile do Diário (professor)
|--------------------------------------------------------------------------
|
| Contrato ESTÁVEL e versionado consumido pelo app Expo (repo separado).
| Autenticação por token (Laravel Sanctum) — guard `sanctum`. CORS restrito
| a `api/v1/*` (config/cors.php). Ver docs/app-diario-mobile/PLANO.md.
|
| Prefixo efetivo: /api/v1  (o "/api" vem do withRouting(api:) em bootstrap/app.php).
|
*/

Route::prefix('v1')->group(function () {
    // ---- Público (sem token) ----
    // throttle:6,1 — anti brute force no login (o rate-limit por login+ip fica no request).
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // ---- Autenticado por token ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Contexto "minhas turmas" do Diário regular (role professor; admin passa).
        Route::middleware('role:professor')
            ->prefix('diario/contexto')
            ->group(function () {
                Route::get('anos-letivos', [ContextoController::class, 'anosLetivos']);
                Route::get('escolas', [ContextoController::class, 'escolas']);
                Route::get('turmas', [ContextoController::class, 'turmas']);
                Route::get('disciplinas', [ContextoController::class, 'disciplinas']);
                Route::get('unidades', [ContextoController::class, 'unidades']);
            });
    });
});
