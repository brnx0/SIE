<?php

namespace App\Http\Controllers\Alimentacao;

use App\Models\Alimentacao\TipoQuestionario;

class TipoQuestionarioController extends CatalogoSimplesController
{
    protected function model(): string
    {
        return TipoQuestionario::class;
    }

    protected function prefixo(): string
    {
        return 'tpq';
    }

    protected function temOrdem(): bool
    {
        return true;
    }

    protected function config(): array
    {
        return [
            'rota'      => 'alimentacao.tipos-questionario',
            'singular'  => 'Tipo de questionário',
            'plural'    => 'Tipos de Questionário',
            'artigo'    => 'o',
            'descricao' => 'Agrupa as perguntas aplicadas nas visitas técnicas. As perguntas ficam na tela de Perguntas.',
        ];
    }
}
