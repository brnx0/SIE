<?php

namespace App\Support;

/**
 * Formatação de telefone para exibição/exportação.
 *
 * Os números são gravados sem máscara no banco (char/varchar de dígitos). Em
 * edu_funcionario o DDD vem embutido nos 11 dígitos; em edu_escola ele mora em
 * coluna própria (esc_ddd) e precisa ser concatenado antes de formatar.
 */
final class Telefone
{
    /** (DD) NNNNN-NNNN para celular, (DD) NNNN-NNNN para fixo. Devolve os dígitos crus se não casar. */
    public static function formatar(?string $bruto): ?string
    {
        $d = preg_replace('/\D/', '', $bruto ?? '');

        if ($d === '') {
            return null;
        }

        if (strlen($d) === 11) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
        }

        if (strlen($d) === 10) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
        }

        return $d;
    }

    /** Junta o DDD (coluna separada) ao número antes de formatar. */
    public static function comDdd(?string $ddd, ?string $numero): ?string
    {
        return $numero ? self::formatar(($ddd ?? '') . $numero) : null;
    }
}
