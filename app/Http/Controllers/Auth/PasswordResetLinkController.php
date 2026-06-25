<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        // Resolve o usuário ativo pelo login. Só dispara o e-mail se ele tiver
        // endereço cadastrado — o broker keya o token pelo e-mail do usuário.
        $user = User::where('login', $request->login)
            ->where('active', true)
            ->first();

        if ($user && $user->email) {
            Password::sendResetLink([
                'login'  => $request->login,
                'active' => true,
            ]);
        }

        // Mensagem genérica: nunca revela se o login/e-mail existe (anti-enumeração).
        return back()->with('status', 'Se o login existir e tiver e-mail cadastrado, enviaremos um link para redefinir a senha.');
    }
}
