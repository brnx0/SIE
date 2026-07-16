<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\Marca;

class MarcaController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return Marca::class;
    }

    protected function prefixo(): string
    {
        return 'mrc';
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.marcas',
            'singular'  => 'Marca',
            'plural'    => 'Marcas',
            'artigo'    => 'a',
            'descricao' => 'Marcas dos itens recebidos. A marca é registrada no lote, a cada entrada.',
        ];
    }
}
