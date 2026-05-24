<?php

namespace App\Http\Middleware;

use App\Models\Parametro\ParametroEntidade;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'params' => fn () => $this->parametrosFlags(),
        ]);
    }

    /**
     * Flags de cadastro expostas ao frontend.
     * Lazy: só consulta DB se inertia request precisar.
     *
     * @return array<string, bool|string|null>
     */
    protected function parametrosFlags(): array
    {
        try {
            $p = ParametroEntidade::current();
        } catch (\Throwable) {
            $p = null;
        }

        return [
            'nome_pessoa_caixa_alta' => (bool) ($p->par_fl_nome_pessoa_caixa_alta ?? true),
            'nome_escola_caixa_alta' => (bool) ($p->par_fl_nome_escola_caixa_alta ?? true),
            'alertar_homonimos' => (bool) ($p->par_fl_alertar_homonimos ?? false),
            'alertar_acentos_nomes' => (bool) ($p->par_fl_alertar_acentos_nomes ?? false),
            'validar_idade_serie' => (bool) ($p->par_fl_validar_idade_serie ?? false),
            'gerar_matricula_auto' => (bool) ($p->par_fl_gerar_matricula_auto ?? true),
            'validar_carga_prof' => (bool) ($p->par_fl_validar_carga_prof ?? false),
            'cpf_obrigatorio' => (bool) ($p->par_fl_cpf_obrigatorio ?? false),
            'fardamento_obrigatorio' => (bool) ($p->par_fl_fardamento_obrigatorio ?? false),
            'tipo_validacao_carga' => $p->par_tipo_validacao_carga ?? 'avisar',
        ];
    }
}
