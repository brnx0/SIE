<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o acesso ao app mobile do Diário às roles de professor.
 *
 * DIFERENTE do middleware `role:` (EnsureUserHasRole), este NÃO tem bypass de
 * admin: por enquanto SOMENTE `professor` e `professor_aee` entram no app —
 * admin/secretaria/gestão usam o sistema web, não o app.
 *
 * Fonte única das roles permitidas (reusada no AuthController::login).
 */
class EnsureAppAccess
{
    /** @var string[] Roles com acesso ao app (sem bypass de admin). */
    public const ROLES = ['professor', 'professor_aee'];

    /**
     * Sem parâmetro, exige qualquer role de professor (self::ROLES).
     * Com parâmetro (`app.access:professor_aee`), restringe àquelas roles — é
     * assim que o diário AEE fica só para quem realmente atende AEE, espelhando
     * o `abortIfNotProfessorAee` do web.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $permitidas = $roles ?: self::ROLES;
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($permitidas)) {
            abort(403, $roles ? 'Acesso restrito ao professor AEE.' : 'Acesso restrito a professores.');
        }

        return $next($request);
    }
}
