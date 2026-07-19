<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAppAccess;
use App\Http\Requests\Api\V1\LoginApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação por TOKEN da API v1 (app mobile do Diário).
 *
 * Não usa sessão: valida credenciais direto no provider e emite um personal
 * access token do Sanctum. O app envia `Authorization: Bearer <token>` nas
 * demais rotas api/v1/* (guard `sanctum`).
 */
class AuthController extends Controller
{
    /** POST api/v1/login → { token, user } */
    public function login(LoginApiRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::query()
            ->where('login', $request->input('login'))
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if (! $user->active) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'login' => 'Usuário inativo. Procure a secretaria.',
            ]);
        }

        // Acesso ao app é restrito a professor / professor_aee (por enquanto).
        // Sem bypass de admin — mesma regra do middleware app.access.
        if (! $user->hasAnyRole(EnsureAppAccess::ROLES)) {
            throw ValidationException::withMessages([
                'login' => 'Acesso restrito a professores. Use o sistema web.',
            ]);
        }

        $request->clearRateLimiter();

        $token = $user->createToken($request->deviceName())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->resumoUsuario($user),
        ]);
    }

    /** GET api/v1/me → { user } */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->resumoUsuario($request->user()),
        ]);
    }

    /** POST api/v1/logout → 204 (revoga apenas o token atual). */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * Payload público do usuário: identidade + roles + vínculo de funcionário
     * (base para "minhas turmas"). Nunca expõe senha/token.
     */
    private function resumoUsuario(User $user): array
    {
        $funNome = null;
        if ($user->fun_id) {
            $funNome = DB::table('edu_funcionario')
                ->where('fun_id', $user->fun_id)
                ->value('fun_nome');
        }

        return [
            'id'       => $user->id,
            'name'     => $user->name,
            'login'    => $user->login,
            'email'    => $user->email,
            'roles'    => $user->roles,
            'fun_id'   => $user->fun_id,
            'fun_nome' => $funNome ?? $user->name,
        ];
    }
}
