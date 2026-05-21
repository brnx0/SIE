<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida dígitos verificadores de CPF (algoritmo Receita Federal).
 * Espera string com exatamente 11 dígitos (sem máscara).
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{11}$/', $value)) {
            $fail('O :attribute não é um CPF válido.');
            return;
        }

        // Rejeita sequências repetidas (000.000.000-00, 111.111.111-11, etc.)
        if (preg_match('/^(\d)\1{10}$/', $value)) {
            $fail('O :attribute não é um CPF válido.');
            return;
        }

        // Dígito verificador 1
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $value[$i] * (10 - $i);
        }
        $d1 = ($sum * 10) % 11;
        if ($d1 === 10) {
            $d1 = 0;
        }
        if ((int) $value[9] !== $d1) {
            $fail('O :attribute não é um CPF válido.');
            return;
        }

        // Dígito verificador 2
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $value[$i] * (11 - $i);
        }
        $d2 = ($sum * 10) % 11;
        if ($d2 === 10) {
            $d2 = 0;
        }
        if ((int) $value[10] !== $d2) {
            $fail('O :attribute não é um CPF válido.');
        }
    }
}
