<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Support\CargoGestao;
use App\Support\Telefone;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EquipeGestoraController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/EquipeGestora/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            'isAdmin'    => $user->isAdmin(),
            'tipos'      => collect(CargoGestao::ROTULOS)
                ->map(fn ($rotulo, $tipo) => ['id' => $tipo, 'label' => $rotulo])
                ->values(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ] = $this->montar($request);

        return Inertia::render('relatorios/EquipeGestora/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['filtros' => $filtros, 'linhas' => $linhas] = $this->montar($request);

        $tipos = $filtros['tipos'];
        $nome  = 'equipe_gestora_' . now()->format('Ymd_His') . '.xlsx';

        if ($filtros['formato'] === 'detalhado') {
            $headers = ['Escola', 'Código', 'Função', 'Cargo', 'Nome', 'Telefone', 'E-mail', 'Vínculo', 'Desde', 'Telefone da Escola', 'E-mail da Escola'];

            $rows = [];
            foreach ($linhas as $l) {
                foreach ($tipos as $tipo) {
                    foreach ($l['gestores'][$tipo] as $g) {
                        $rows[] = [
                            $l['esc_nome'],
                            $l['esc_codigo'] ?? '',
                            CargoGestao::rotulo($tipo),
                            $g['cargo'] ?? '',
                            $g['nome'] ?? '',
                            $g['telefone'] ?? '',
                            $g['email'] ?? '',
                            $g['vinculo'] ?? '',
                            $g['desde'] ?? '',
                            $l['telefone'] ?? '',
                            $l['email'] ?? '',
                        ];
                    }
                }
            }

            return XlsxExport::download($nome, $headers, $rows);
        }

        $headers = array_merge(
            ['Escola', 'Código'],
            array_map(fn ($t) => CargoGestao::rotulo($t), $tipos),
            ['Telefone', 'E-mail'],
        );

        $rows = $linhas->map(fn ($l) => array_merge(
            [$l['esc_nome'], $l['esc_codigo'] ?? ''],
            array_map(fn ($t) => $this->nomesEmLinha($l['gestores'][$t]), $tipos),
            [$l['telefone'] ?? '', $l['email'] ?? ''],
        ))->all();

        return XlsxExport::download($nome, $headers, $rows);
    }

    /**
     * Consolida escolas + equipe gestora (compartilhado entre tela e export).
     *
     * Uma linha por escola visível ao usuário, com os gestores agrupados por
     * função. Escolas sem gestor continuam na lista — a lacuna é justamente o
     * que o relatório precisa evidenciar (ver 'resumo').
     *
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        // Tipo de gestor é OBRIGATÓRIO (o form marca como tal): sem isto, uma
        // requisição sem `tipos` caía no "todos" e o relatório trazia funções
        // que o usuário não pediu.
        $data = $request->validate([
            'esc_id'  => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tipos'   => ['required', 'array', 'min:1'],
            'tipos.*' => ['string', 'in:' . implode(',', CargoGestao::tipos())],
            'formato' => ['nullable', 'in:consolidado,detalhado'],
        ], [
            'tipos.required' => 'Selecione ao menos um tipo de gestor.',
            'tipos.min'      => 'Selecione ao menos um tipo de gestor.',
        ], ['tipos' => 'tipo de gestor']);

        // Preserva a ordem hierárquica de CargoGestao, não a ordem que veio na URL.
        $tipos = array_values(array_filter(
            CargoGestao::tipos(),
            fn ($t) => in_array($t, $data['tipos'], true),
        ));

        $formato = $data['formato'] ?? 'consolidado';

        $escolas = UserAccess::escolasVisiveis(auth()->user());

        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }

        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $detalhes = $escolasIds
            ? Escola::whereIn('esc_id', $escolasIds)
                ->get(['esc_id', 'esc_cd_escola', 'esc_cd_inep', 'esc_ddd', 'esc_telefone_fixo', 'esc_telefone_2', 'esc_email'])
                ->keyBy('esc_id')
            : collect();

        $porEscola = $this->gestoresPorEscola($escolasIds, $tipos);

        $vazio = array_fill_keys($tipos, []);

        $linhas = $escolas->map(function ($e) use ($detalhes, $porEscola, $vazio) {
            $d = $detalhes->get($e->esc_id);
            $gestores = array_replace($vazio, $porEscola->get($e->esc_id, []));

            return [
                'esc_id'         => (int) $e->esc_id,
                'esc_nome'       => $e->esc_nome,
                'esc_codigo'     => $d?->esc_cd_escola ?: $d?->esc_cd_inep,
                'esc_inep'       => $d?->esc_cd_inep,
                'telefone'       => $this->telefoneEscola($d),
                'email'          => $d?->esc_email,
                'gestores'       => $gestores,
                'total_gestores' => array_sum(array_map('count', $gestores)),
            ];
        })->values();

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros ? $parametros->cabecalhoRelatorio() : null,
            'filtros' => [
                'esc_id'   => ! empty($data['esc_id']) ? (int) $data['esc_id'] : null,
                'esc_nome' => ! empty($data['esc_id']) ? ($escolas->first()->esc_nome ?? null) : null,
                'tipos'    => $tipos,
                'rotulos'  => array_map(fn ($t) => CargoGestao::rotulo($t), $tipos),
                'formato'  => $formato,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'escolas'          => $linhas->count(),
                'gestores'         => $linhas->sum('total_gestores'),
                'sem_diretor'      => in_array(CargoGestao::DIRETOR, $tipos, true)
                    ? $linhas->filter(fn ($l) => empty($l['gestores'][CargoGestao::DIRETOR]))->count()
                    : null,
                'sem_nenhum'       => $linhas->filter(fn ($l) => $l['total_gestores'] === 0)->count(),
            ],
        ];
    }

    /**
     * Gestores das escolas, agrupados por esc_id e depois por tipo de função.
     *
     * Considera apenas lotação ativa e vigente hoje, de funcionário ativo.
     *
     * @param  array<int>     $escolasIds
     * @param  array<string>  $tipos
     * @return Collection<int, array<string, array>>
     */
    private function gestoresPorEscola(array $escolasIds, array $tipos): Collection
    {
        if (! $escolasIds) {
            return collect();
        }

        $hoje = now()->toDateString();

        $lotacoes = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'a.adm_fun_id')
            ->join('edu_cargo as cg', 'cg.crg_id', '=', 'l.lot_crg_id')
            ->whereIn('l.lot_esc_id', $escolasIds)
            ->where('l.lot_fl_ativo', true)
            ->where('f.fun_fl_ativo', true)
            ->whereNull('f.fun_deleted_at')
            ->whereNull('a.adm_deleted_at')
            ->where(fn ($q) => $q->whereNull('l.lot_dt_fim')->orWhere('l.lot_dt_fim', '>=', $hoje))
            ->get([
                'l.lot_esc_id', 'l.lot_vinculo', 'l.lot_dt_inicio',
                'cg.crg_nome',
                'f.fun_id', 'f.fun_nome', 'f.fun_nome_social', 'f.fun_telefone', 'f.fun_celular', 'f.fun_email',
            ]);

        return collect($lotacoes)
            ->map(function ($r) {
                $r->tipo = CargoGestao::classificar($r->crg_nome);

                return $r;
            })
            ->filter(fn ($r) => $r->tipo !== null && in_array($r->tipo, $tipos, true))
            // Mesmo funcionário pode ter 2 admissões/lotações no mesmo cargo e escola.
            ->unique(fn ($r) => $r->lot_esc_id . '|' . $r->fun_id . '|' . $r->crg_nome)
            ->groupBy('lot_esc_id')
            ->map(fn (Collection $daEscola) => $daEscola
                ->sortBy([
                    fn ($r) => $this->chaveOrdenacao($r->crg_nome),
                    fn ($r) => $this->chaveOrdenacao($r->fun_nome),
                ])
                ->groupBy('tipo')
                ->map(fn (Collection $doTipo) => $doTipo->map(fn ($r) => [
                    'fun_id'   => (int) $r->fun_id,
                    'nome'     => $r->fun_nome_social ?: $r->fun_nome,
                    'cargo'    => $r->crg_nome,
                    'telefone' => Telefone::formatar($r->fun_celular ?: $r->fun_telefone),
                    'email'    => $r->fun_email,
                    'vinculo'  => $r->lot_vinculo,
                    'desde'    => $r->lot_dt_inicio ? Carbon::parse($r->lot_dt_inicio)->format('d/m/Y') : null,
                ])->values()->all())
                ->all())
            // groupBy devolve chaves string; o front indexa por esc_id numérico.
            ->keyBy(fn ($v, $k) => (int) $k);
    }

    /** Nomes de um grupo em linha única, para CSV/XLSX. */
    private function nomesEmLinha(array $gestores): string
    {
        return implode(' / ', array_column($gestores, 'nome'));
    }

    /** Telefone da escola: DDD fica em coluna própria (esc_ddd). */
    private function telefoneEscola($escola): ?string
    {
        return $escola
            ? Telefone::comDdd($escola->esc_ddd, $escola->esc_telefone_fixo ?: $escola->esc_telefone_2)
            : null;
    }

    /** Chave de ordenação alfabética insensível a acento/caixa. */
    private function chaveOrdenacao(?string $nome): string
    {
        $s = mb_strtolower($nome ?? '', 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);

        return $t !== false ? $t : $s;
    }
}
