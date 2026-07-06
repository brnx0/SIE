<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida dígitos verificadores de CNPJ (algoritmo Receita Federal).
 *
 * Aceita o CNPJ tradicional (14 dígitos) e o novo CNPJ ALFANUMÉRICO
 * (vigente a partir de julho/2026): 12 posições alfanuméricas [0-9A-Z]
 * seguidas de 2 dígitos verificadores numéricos.
 *
 * O cálculo dos DVs usa o valor de cada caractere = (código ASCII − 48),
 * de modo que '0'..'9' → 0..9 e 'A'..'Z' → 17..42, com os mesmos pesos do
 * CNPJ tradicional (que passa a ser um caso particular deste algoritmo).
 *
 * Espera string já normalizada (14 caracteres, maiúscula, sem máscara).
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $value)) {
            $fail('O :attribute não é válido.');

            return;
        }

        // Rejeita caractere repetido nas 14 posições (ex.: 00000000000000).
        if (preg_match('/^(.)\1{13}$/', $value)) {
            $fail('O :attribute não é válido.');

            return;
        }

        $d1 = $this->digito($value, 12);
        $d2 = $this->digito($value, 13);

        if ((int) $value[12] !== $d1 || (int) $value[13] !== $d2) {
            $fail('O :attribute não é válido.');
        }
    }

    /** Calcula um dígito verificador sobre os primeiros $len caracteres. */
    private function digito(string $cnpj, int $len): int
    {
        // Pesos 2..9 aplicados da direita para a esquerda.
        $peso = 2;
        $soma = 0;
        for ($i = $len - 1; $i >= 0; $i--) {
            $soma += (ord($cnpj[$i]) - 48) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $mod = $soma % 11;

        return $mod < 2 ? 0 : 11 - $mod;
    }
}
