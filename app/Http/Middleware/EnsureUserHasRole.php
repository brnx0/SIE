<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Garante que o usuário autenticado possua ao menos uma das roles informadas.
     * Admin sempre passa.
     *
     * Uso: ->middleware('role:coordenador') ou 'role:coordenador,diretor'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->hasAnyRole($roles))) {
            abort(403, 'Acesso negado: perfil sem permissão.');
        }

        return $next($request);
    }
}
