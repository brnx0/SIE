<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerificaPermissaoTela;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            'tela' => VerificaPermissaoTela::class,
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

        // Sessão expirada → não autenticado: redireciona ao login COM aviso.
        // (Sem isto, o usuário só é deslogado, sem mensagem.)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão expirou.'], 401);
            }
            return redirect()->guest(route('login'))
                ->with('error', 'Sua sessão expirou. Faça login novamente.');
        });

        // 403 (sem permissão): numa navegação Inertia, volta com flash → toast
        // amigável (não modal cru). Full-page cai na view errors/403.blade.php.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() !== 403 || ! $request->header('X-Inertia')) {
                return null;
            }
            $msg = $e->getMessage() ?: 'Você não tem permissão para acessar este recurso.';
            return back()->with('error', $msg);
        });
    })->create();
