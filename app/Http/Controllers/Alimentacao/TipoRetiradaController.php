<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\TipoRetirada;

class TipoRetiradaController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return TipoRetirada::class;
    }

    protected function prefixo(): string
    {
        return 'tpr';
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.tipos-retirada',
            'singular'  => 'Tipo de retirada',
            'plural'    => 'Tipos de Retirada',
            'artigo'    => 'o',
            'descricao' => 'Motivos de retirada de item do estoque escolar (uso na cozinha, descarte, vencimento...).',
        ];
    }
}
