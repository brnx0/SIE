<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Support\PermissaoTela;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioCentralController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('central'),
            'titulo' => 'Central de Relatórios',
            'subtitulo' => 'Selecione um relatório para gerar.',
            'grupo' => 'central',
        ]);
    }

    public function escola(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('escola'),
            'titulo' => 'Relatórios Gerais',
            'subtitulo' => 'Relatórios específicos do contexto da escola.',
            'grupo' => 'escola',
        ]);
    }

    public function diario(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('diario'),
            'titulo' => 'Relatórios do Diário',
            'subtitulo' => 'Relatórios do diário de classe.',
            'grupo' => 'diario',
        ]);
    }

    public function secretaria(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('secretaria'),
            'titulo' => 'Relatórios da Secretaria',
            'subtitulo' => 'Relatórios acadêmicos da secretaria escolar.',
            'grupo' => 'secretaria',
        ]);
    }

    public function pedagogico(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('pedagogico'),
            'titulo' => 'Relatórios Pedagógicos',
            'subtitulo' => 'Relatórios do acompanhamento pedagógico.',
            'grupo' => 'pedagogico',
        ]);
    }

    public function alimentacao(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('alimentacao'),
            'titulo' => 'Relatórios da Alimentação',
            'subtitulo' => 'Relatórios da alimentação escolar (PNAE).',
            'grupo' => 'alimentacao',
        ]);
    }

    public function censo(): Response
    {
        return Inertia::render('relatorios/Index', [
            'relatorios' => $this->filtrar('censo'),
            'titulo' => 'Relatórios do Censo',
            'subtitulo' => 'Críticas e exportação do Educacenso.',
            'grupo' => 'censo',
        ]);
    }

    /**
     * Cards do grupo que o usuário realmente consegue abrir.
     *
     * O card leva para /relatorios/{slug}, que passa pela matriz cfg_tela como
     * qualquer outra rota. Sem este filtro o hub oferece relatórios que o perfil
     * não tem — o clique morre em 403 e parece defeito. Relatório sem tela
     * própria resolve por prefixo para a tela do hub, então segue aparecendo
     * para quem já enxerga o hub: nada é escondido de quem tinha acesso.
     */
    private function filtrar(string $grupo): array
    {
        $user = request()->user();

        return array_values(array_filter(
            config('relatorios'),
            fn ($r) => ($r['grupo'] ?? 'central') === $grupo && $this->podeAbrir($user, $r['slug']),
        ));
    }

    /** Mesmo veredito que VerificaPermissaoTela dará no clique do card. */
    private function podeAbrir(?\App\Models\User $user, string $slug): bool
    {
        // resolverTela() reproduz o longest-prefix do middleware: relatório sem
        // tela própria herda a tela dona (normalmente o próprio hub).
        $tela = PermissaoTela::resolverTela('/relatorios/' . $slug);

        return $tela === null
            || PermissaoTela::nivelParaRota($user, $tela) > PermissaoTela::SEM_ACESSO;
    }
}
