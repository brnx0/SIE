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
            'titulo'     => 'Relatórios Gerais',
            'subtitulo'  => 'Relatórios específicos do contexto da escola.',
            'grupo'      => 'escola',
        ]);
    }

    public function diario(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('diario'),
            'titulo'     => 'Relatórios do Diário',
            'subtitulo'  => 'Relatórios do diário de classe.',
            'grupo'      => 'diario',
        ]);
    }

    public function secretaria(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('secretaria'),
            'titulo'     => 'Relatórios da Secretaria',
            'subtitulo'  => 'Relatórios acadêmicos da secretaria escolar.',
            'grupo'      => 'secretaria',
        ]);
    }

    public function pedagogico(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('pedagogico'),
            'titulo'     => 'Relatórios Pedagógicos',
            'subtitulo'  => 'Relatórios do acompanhamento pedagógico.',
            'grupo'      => 'pedagogico',
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
