<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\CategoriaAlimento;

class CategoriaAlimentoController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return CategoriaAlimento::class;
    }

    protected function prefixo(): string
    {
        return 'cat';
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.categorias-alimento',
            'singular'  => 'Categoria de alimento',
            'plural'    => 'Categorias de Alimento',
            'artigo'    => 'a',
            'descricao' => 'Agrupa os gêneros alimentícios por categoria (cereais, hortifrúti, carnes...).',
        ];
    }
}
