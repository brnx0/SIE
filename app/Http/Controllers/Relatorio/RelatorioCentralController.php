<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioCentralController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('central'),
            'titulo'     => 'Central de Relatórios',
            'subtitulo'  => 'Selecione um relatório para gerar.',
            'grupo'      => 'central',
        ]);
    }

    public function escola(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('escola'),
            'titulo'     => 'Relatórios da Escola',
            'subtitulo'  => 'Relatórios específicos do contexto da escola.',
            'grupo'      => 'escola',
        ]);
    }

    private function filtrar(string $grupo): array
    {
        return array_values(array_filter(
            config('relatorios'),
            fn ($r) => ($r['grupo'] ?? 'central') === $grupo,
        ));
    }
}
