<?php

namespace App\Services\Censo;

use App\Services\Censo\Contracts\LayoutVersion;
use App\Services\Censo\Layouts\Layout2025;
use RuntimeException;

/**
 * Escolhe a versão do layout: por ano explícito ou por detecção da assinatura
 * (nº de campos por tipo de registro) amostrada do arquivo.
 */
class LayoutResolver
{
    /** @var array<int,class-string<LayoutVersion>> */
    private array $versions = [
        2025 => Layout2025::class,
    ];

    public function byYear(int $year): LayoutVersion
    {
        if (! isset($this->versions[$year])) {
            throw new RuntimeException("Layout do Censo {$year} não implementado.");
        }

        return new $this->versions[$year];
    }

    /**
     * Detecta a versão comparando a assinatura amostrada com as conhecidas.
     *
     * @param  array<string,int>  $sampleSignature  tipo => nº de campos observado
     */
    public function detect(array $sampleSignature): LayoutVersion
    {
        foreach ($this->versions as $class) {
            /** @var LayoutVersion $version */
            $version = new $class;
            if ($this->matches($version->signature(), $sampleSignature)) {
                return $version;
            }
        }

        throw new RuntimeException(
            'Layout do arquivo não corresponde a nenhuma versão conhecida. '
            .'Assinatura observada: '.json_encode($sampleSignature)
        );
    }

    /**
     * Bate se todo tipo observado tem o nº de campos esperado pela versão.
     * (Ignora tipos ausentes na amostra.)
     */
    private function matches(array $expected, array $sample): bool
    {
        foreach ($sample as $tipo => $count) {
            if (isset($expected[$tipo]) && $expected[$tipo] !== $count) {
                return false;
            }
        }

        return count(array_intersect_key($expected, $sample)) > 0;
    }
}
