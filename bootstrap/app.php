<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Atras de proxy reverso (nginx host -> container). Le X-Forwarded-Proto
        // p/ request->secure() funcionar e evitar loop de redirect do ForceHttps.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->prepend(ForceHttps::class);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada. Recarregue a página.'], 419);
            }
            $request->session()->regenerateToken();
            return redirect($request->url())
                ->with('error', 'Sua sessão expirou. Por favor, tente novamente.');
        });
    })->create();
