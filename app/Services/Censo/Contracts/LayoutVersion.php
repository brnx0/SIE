<?php

namespace App\Services\Censo\Contracts;

/**
 * Contrato de uma versão anual do layout de migração do Censo.
 * Layout muda a cada ano — cada versão isola posições de coluna.
 * Ver docs/regras/censo_layout_2025.md.
 */
interface LayoutVersion
{
    /** Ano do layout (ex.: 2025). */
    public function year(): int;

    /**
     * Assinatura: tipo de registro => nº esperado de campos.
     * Usada para detectar a versão pelo arquivo.
     *
     * @return array<string,int>
     */
    public function signature(): array;

    /**
     * Mapa posição (1-based, col 1 = tipo) => nome do campo, para um tipo de registro.
     * Só as colunas de interesse; o array bruto completo é preservado à parte.
     *
     * @return array<int,string>
     */
    public function fieldMap(string $tipo): array;

    /** Tipos de registro válidos (fora o marcador de fim 99). */
    public function tiposValidos(): array;
}
