<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Central de Ajuda — pública (sem autenticação).
 *
 * Lê os artigos de `resources/ajuda/**.md`, converte Markdown → HTML e
 * agrupa por seção (frontmatter). Conteúdo versionado no git: editar/adicionar
 * um `.md` publica sem deploy de código.
 */
class AjudaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ajuda/Index', [
            'secoes' => $this->carregarArtigos(),
        ]);
    }

    /**
     * @return array<int, array{secao:string, ordem:int, artigos:array<int, array<string, mixed>>}>
     */
    private function carregarArtigos(): array
    {
        $base = resource_path('ajuda');

        if (! File::isDirectory($base)) {
            return [];
        }

        $converter = $this->converter();
        $artigos = [];

        foreach (File::allFiles($base) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $resultado = $converter->convert($file->getContents());
            $fm = $resultado instanceof RenderedContentWithFrontMatter
                ? $resultado->getFrontMatter()
                : [];

            $titulo = (string) ($fm['titulo'] ?? Str::headline(pathinfo($file->getFilename(), PATHINFO_FILENAME)));
            $html = $resultado->getContent();

            $artigos[] = [
                'slug' => (string) ($fm['slug'] ?? Str::slug($titulo)),
                'titulo' => $titulo,
                'secao' => (string) ($fm['secao'] ?? 'Geral'),
                'ordem_secao' => (int) ($fm['ordem_secao'] ?? 999),
                'ordem' => (int) ($fm['ordem'] ?? 999),
                'resumo' => isset($fm['resumo']) ? (string) $fm['resumo'] : null,
                'html' => $html,
                // Texto plano para a busca client-side (sem tags/whitespace redundante).
                'texto' => trim((string) preg_replace('/\s+/', ' ', strip_tags($html))),
            ];
        }

        return collect($artigos)
            ->groupBy('secao')
            ->map(fn ($itens, $secao) => [
                'secao' => (string) $secao,
                'ordem' => (int) $itens->min('ordem_secao'),
                'artigos' => $itens
                    ->sortBy([['ordem', 'asc'], ['titulo', 'asc']])
                    ->values()
                    ->map(fn ($a) => collect($a)->except('ordem_secao')->all())
                    ->all(),
            ])
            ->sortBy([['ordem', 'asc'], ['secao', 'asc']])
            ->values()
            ->all();
    }

    private function converter(): MarkdownConverter
    {
        $env = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new FrontMatterExtension());

        return new MarkdownConverter($env);
    }
}
