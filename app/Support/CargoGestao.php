<?php

namespace App\Support;

/**
 * Fonte única para classificar um cargo como função da EQUIPE GESTORA da escola.
 *
 * Não existe coluna de "função de gestão" em edu_funcionario_lotacao — a única
 * coluna de função (lot_funcoes_sala_aula) cobre só docência. Portanto a
 * classificação é feita pelo NOME do cargo (edu_cargo.crg_nome), no mesmo
 * espírito de CargoDocente e de CensoExportService::gestores().
 *
 * Complementa CargoDocente: aquele responde "atua em sala de aula?", este
 * responde "faz parte da equipe gestora e em qual função?".
 */
final class CargoGestao
{
    public const DIRETOR = 'diretor';

    public const VICE_DIRETOR = 'vice_diretor';

    public const COORD_PEDAGOGICO = 'coord_pedagogico';

    public const SECRETARIO = 'secretario';

    /** Rótulos exibidos ao usuário, na ordem hierárquica do relatório. */
    public const ROTULOS = [
        self::DIRETOR             => 'Diretor(a)',
        self::VICE_DIRETOR        => 'Vice-Diretor(a)',
        self::COORD_PEDAGOGICO    => 'Coordenador(a) Pedagógico(a)',
        self::SECRETARIO          => 'Secretário(a) Escolar',
    ];

    /** @return array<int, string> Tipos válidos na ordem hierárquica. */
    public static function tipos(): array
    {
        return array_keys(self::ROTULOS);
    }

    public static function rotulo(?string $tipo): ?string
    {
        return $tipo ? (self::ROTULOS[$tipo] ?? null) : null;
    }

    /**
     * Classifica um nome de cargo em uma das funções de gestão.
     *
     * Retorna null quando o cargo não é de gestão (docente, apoio, serviços...).
     * A ordem das checagens importa: "Diretor(a) Adjunto(a)" e "Vice-Diretor"
     * precisam cair em VICE_DIRETOR antes de casar com o teste de "diretor".
     */
    public static function classificar(?string $nome): ?string
    {
        $n = self::normalizar($nome);

        if ($n === '') {
            return null;
        }

        $ehDirecao = str_contains($n, 'diretor')
            || str_contains($n, 'dirigente')
            || str_contains($n, 'gestor escolar');

        if ($ehDirecao) {
            return str_contains($n, 'vice') || str_contains($n, 'adjunt')
                ? self::VICE_DIRETOR
                : self::DIRETOR;
        }

        if (str_contains($n, 'secretari')) {
            return self::SECRETARIO;
        }

        $ehCoordenacao = str_contains($n, 'coordenador')
            || str_contains($n, 'supervisor')
            || str_contains($n, 'orientador')
            || str_contains($n, 'especialista de educacao');

        // Sem a distinção administrativa, toda coordenação de gestão cai no
        // único bucket de coordenação restante (Pedagógico).
        if ($ehCoordenacao) {
            return self::COORD_PEDAGOGICO;
        }

        return null;
    }

    /**
     * Minúsculas e sem acento, para comparar nome de cargo com segurança.
     *
     * Usa mapa explícito em vez de iconv('ASCII//TRANSLIT'): dependendo da libc
     * o TRANSLIT devolve "pedag'ogico" (com apóstrofo) em vez de "pedagogico",
     * e a checagem de "pedagog" falharia silenciosamente.
     */
    private static function normalizar(?string $nome): string
    {
        $s = mb_strtolower(trim($nome ?? ''), 'UTF-8');

        return strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
