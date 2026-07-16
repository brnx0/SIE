<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\TipoVisita;

class TipoVisitaController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return TipoVisita::class;
    }

    protected function prefixo(): string
    {
        return 'tpv';
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.tipos-visita',
            'singular'  => 'Tipo de visita',
            'plural'    => 'Tipos de Visita Técnica',
            'artigo'    => 'o',
            'descricao' => 'Tipos de visita técnica realizados nas unidades escolares.',
        ];
    }
}
