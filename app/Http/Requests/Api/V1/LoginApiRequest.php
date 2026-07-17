<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login da API v1 (app mobile). Espelha as regras do LoginRequest web
 * (normaliza `login`, mesmo rate-limit) mas NÃO abre sessão — a emissão do
 * token de acesso (Sanctum) é feita no AuthController.
 */
class LoginApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normaliza o login para minúsculas antes de validar (igual ao fluxo web). */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => strtolower(trim((string) $this->input('login'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login'       => ['required', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** Nome do dispositivo p/ rotular o token; fallback genérico. */
    public function deviceName(): string
    {
        $name = trim((string) $this->input('device_name'));

        return $name !== '' ? Str::limit($name, 120, '') : 'app-mobile';
    }

    // ---- Rate limiting (5 tentativas por login+ip, igual ao web) ----

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return 'api-login:'.Str::transliterate(Str::lower((string) $this->input('login')).'|'.$this->ip());
    }
}
