<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\TipoItem;

class TipoItemController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return TipoItem::class;
    }

    protected function prefixo(): string
    {
        return 'tpi';
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.tipos-item',
            'singular'  => 'Tipo de item',
            'plural'    => 'Tipos de Item',
            'artigo'    => 'o',
            'descricao' => 'Classifica os itens usados na composição do cardápio e no controle de estoque.',
        ];
    }
}
