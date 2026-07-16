<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Cardapio;
use App\Models\Alimentacao\Preparacao;
use App\Models\Alimentacao\PreparacaoItem;
use App\Models\Escola\Escola;
use App\Models\Turma\Turma;
use App\Services\Alimentacao\ConfirmaPreparacao;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controle diário da preparação da merenda.
 *
 * A escola registra o que preparou; confirmar é o ato que baixa o estoque da
 * unidade. Perfil com lotação só enxerga e opera a própria escola.
 */
class PreparacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $escId   = $this->escolaEscolhida($request);
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $preparacoes = $this->baseQuery($request, $escId)
            ->with(['prato:prt_id,prt_nome', 'escola:esc_id,esc_nome', 'itens'])
            ->orderByDesc('prd_dt')
            ->orderByDesc('prd_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($p) => [
                'prd_id'          => $p->prd_id,
                'prd_dt'          => $p->prd_dt?->toDateString(),
                'prd_turno'       => $p->prd_turno,
                'prd_refeicao'    => $p->prd_refeicao,
                'prd_qtd_servida' => $p->prd_qtd_servida,
                'prd_situacao'    => $p->prd_situacao,
                'prato_nome'      => $p->prato?->prt_nome,
                'escola_nome'     => $p->escola?->esc_nome,
                'itens_count'     => $p->itens->count(),
            ]);

        return Inertia::render('alimentacao/preparacoes/Index', [
            'preparacoes'    => $preparacoes,
            // O EscolaCombobox busca em /api/escolas (já escopado por lotação);
            // aqui vai só a escola do filtro atual, para ele exibir o nome.
            'escolaFiltro'   => $this->escolaResumo($escId),
            'situacoes'      => Preparacao::SITUACOES,
            'turnos'         => Turma::TURNOS,
            'filters'        => [
                'esc_id'   => $request->input('esc_id', ''),
                'situacao' => $request->input('situacao', ''),
                'turno'    => $request->input('turno', ''),
                'dt_ini'   => $request->input('dt_ini', ''),
                'dt_fim'   => $request->input('dt_fim', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $escId = $this->escolaEscolhida($request);
        $dt    = $request->input('dt', now()->toDateString());

        return Inertia::render('alimentacao/preparacoes/Create', [
            'escolaInicial' => $this->escolaResumo($escId),
            'turnos'      => Turma::TURNOS,
            'refeicoes'   => CardapioController::REFEICOES,
            'dt'          => $dt,
            // O cardápio do dia é a sugestão do que deveria ser servido — evita
            // a escola caçar o prato na mão e sair do cardápio sem perceber.
            'sugestoes'   => $escId ? $this->sugestoesDoCardapio($escId, $dt) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            [
                'prd_esc_id'      => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
                'prd_prt_id'      => ['required', 'integer', 'exists:ali_prato,prt_id'],
                'prd_fun_id'      => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
                'prd_dt'          => ['required', 'date'],
                'prd_turno'       => ['required', 'string', Rule::in(Turma::TURNOS)],
                'prd_refeicao'    => ['required', 'string', Rule::in(CardapioController::REFEICOES)],
                'prd_qtd_servida' => ['required', 'integer', 'min:1'],
                'prd_obs'         => ['nullable', 'string'],
            ],
            [
                'prd_qtd_servida.min' => 'Informe quantas porções foram servidas.',
            ],
            [
                'prd_esc_id'      => 'escola',
                'prd_prt_id'      => 'prato',
                'prd_fun_id'      => 'responsável',
                'prd_dt'          => 'data',
                'prd_turno'       => 'turno',
                'prd_refeicao'    => 'refeição',
                'prd_qtd_servida' => 'porções servidas',
            ],
        );

        $dados['prd_esc_id'] = $this->resolverEscola($request, $dados['prd_esc_id'] ?? null);

        $this->assertSlotLivre($dados);

        $preparacao = Preparacao::create($dados + [
            'prd_situacao' => Preparacao::SITUACAO_RASCUNHO,
            'prd_usr_id'   => $request->user()?->id,
        ]);

        // Monta os itens pela ficha técnica. Prato sem ficha rejeita aqui, antes
        // de deixar um registro que não baixaria estoque nenhum.
        try {
            app(ConfirmaPreparacao::class)->montarItens($preparacao);
        } catch (ValidationException $e) {
            $preparacao->forceDelete();
            throw $e;
        }

        return to_route('alimentacao.preparacoes.edit', $preparacao->prd_id)
            ->with('success', 'Preparação aberta. Ajuste o que foi realmente utilizado e confirme para baixar o estoque.');
    }

    public function edit(int $id): Response
    {
        $preparacao = Preparacao::with([
            'prato:prt_id,prt_nome',
            'escola:esc_id,esc_nome',
            'itens.item:itm_id,itm_nome,itm_umd_id',
            'itens.item.unidadeMedida:umd_id,umd_sigla',
            'itens.unidadeMedida:umd_id,umd_sigla',
        ])->findOrFail($id);

        $this->assertPodeVer($preparacao);

        return Inertia::render('alimentacao/preparacoes/Edit', [
            'preparacao' => [
                'prd_id'          => $preparacao->prd_id,
                'prd_dt'          => $preparacao->prd_dt?->toDateString(),
                'prd_turno'       => $preparacao->prd_turno,
                'prd_refeicao'    => $preparacao->prd_refeicao,
                'prd_qtd_servida' => $preparacao->prd_qtd_servida,
                'prd_situacao'    => $preparacao->prd_situacao,
                'prd_obs'         => $preparacao->prd_obs,
                'prato_nome'      => $preparacao->prato?->prt_nome,
                'escola_nome'     => $preparacao->escola?->esc_nome,
            ],
            'itens' => $preparacao->itens->map(fn ($i) => [
                'pdi_id'        => $i->pdi_id,
                'item_nome'     => $i->item?->itm_nome,
                'umd_sigla'     => $i->unidadeMedida?->umd_sigla,
                'qtd_prevista'  => (float) $i->pdi_qtd_prevista,
                'qtd_utilizada' => (float) $i->pdi_qtd_utilizada,
                'desvio'        => $i->desvio(),
            ]),
            'emRascunho' => $preparacao->emRascunho(),
        ]);
    }

    /** Ajusta o que foi realmente utilizado de um item. */
    public function ajustarItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        $preparacao = Preparacao::findOrFail($id);
        $this->assertPodeVer($preparacao);
        $this->assertEmRascunho($preparacao);

        $linha = PreparacaoItem::where('pdi_prd_id', $preparacao->prd_id)->findOrFail($itemId);

        $dados = $request->validate(
            ['pdi_qtd_utilizada' => ['required', 'numeric', 'min:0']],
            [],
            ['pdi_qtd_utilizada' => 'quantidade utilizada'],
        );

        $linha->update($dados);

        return back()->with('success', 'Quantidade utilizada atualizada.');
    }

    public function confirmar(Request $request, int $id): RedirectResponse
    {
        $preparacao = Preparacao::findOrFail($id);
        $this->assertPodeVer($preparacao);

        app(ConfirmaPreparacao::class)->confirmar($preparacao, $request->user()?->id);

        return to_route('alimentacao.preparacoes.edit', $id)
            ->with('success', 'Preparação confirmada. Os itens utilizados foram baixados do estoque da escola.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $preparacao = Preparacao::findOrFail($id);
        $this->assertPodeVer($preparacao);

        // Confirmada já baixou estoque: apagar deixaria o saldo sem a origem.
        if (! $preparacao->emRascunho()) {
            return back()->with('error', 'Esta preparação já foi confirmada e não pode ser excluída. Estorne os movimentos de estoque se precisar corrigir.');
        }

        $preparacao->itens()->delete();

        return $this->safeDelete($preparacao)
            ?? to_route('alimentacao.preparacoes.index')->with('success', 'Preparação removida.');
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $escId = $this->escolaEscolhida($request);

        $preparacoes = $this->baseQuery($request, $escId)
            ->with([
                'prato:prt_id,prt_nome', 'escola:esc_id,esc_nome', 'funcionario:fun_id,fun_nome',
                'itens.item:itm_id,itm_nome', 'itens.unidadeMedida:umd_id,umd_sigla',
            ])
            ->orderBy('prd_dt')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($preparacoes, $request, $escId)
            : $this->exportCsv($preparacoes);
    }

    // ------------------------------------------------------------------

    private function escolaEscolhida(Request $request): ?int
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        if ($escolasIds === null) {
            $escId = $request->input('esc_id');

            return $escId === '' || $escId === null ? null : (int) $escId;
        }

        $pedida = (int) $request->input('esc_id');

        return in_array($pedida, $escolasIds, true) ? $pedida : ($escolasIds[0] ?? -1);
    }

    /**
     * Perfil com lotação registra preparação só da própria escola — mandar outro
     * `esc_id` no payload não pode furar isso.
     */
    private function resolverEscola(Request $request, ?int $pedido): int
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        if ($escolasIds === null) {
            if (! $pedido) {
                throw ValidationException::withMessages([
                    'prd_esc_id' => 'Escolha a escola da preparação.',
                ]);
            }

            return $pedido;
        }

        if ($pedido !== null && ! in_array($pedido, $escolasIds, true)) {
            throw ValidationException::withMessages([
                'prd_esc_id' => 'Você só pode registrar preparação da sua escola.',
            ]);
        }

        return $pedido ?? ($escolasIds[0] ?? -1);
    }

    private function assertPodeVer(Preparacao $preparacao): void
    {
        $escolasIds = UserAccess::escolasIds(request()->user());

        if ($escolasIds === null || in_array($preparacao->prd_esc_id, $escolasIds, true)) {
            return;
        }

        abort(403, 'Esta preparação é de outra escola.');
    }

    private function assertEmRascunho(Preparacao $preparacao): void
    {
        if ($preparacao->emRascunho()) {
            return;
        }

        throw ValidationException::withMessages([
            'pdi_qtd_utilizada' => "Esta preparação está {$preparacao->prd_situacao}: o estoque já foi baixado e os itens não podem mais ser alterados.",
        ]);
    }

    /**
     * O banco tem unique parcial no slot; sem esta checagem o usuário levaria um
     * erro cru do Postgres em vez de mensagem na tela.
     */
    private function assertSlotLivre(array $dados): void
    {
        $existe = Preparacao::query()
            ->where('prd_esc_id', $dados['prd_esc_id'])
            ->whereDate('prd_dt', $dados['prd_dt'])
            ->where('prd_turno', $dados['prd_turno'])
            ->where('prd_refeicao', $dados['prd_refeicao'])
            ->where('prd_situacao', '!=', Preparacao::SITUACAO_CANCELADA)
            ->exists();

        if (! $existe) {
            return;
        }

        throw ValidationException::withMessages([
            'prd_refeicao' => 'Já existe preparação registrada para esta escola neste dia, turno e refeição.'
                .' Edite a que existe — duas dobrariam a baixa do estoque.',
        ]);
    }

    /** O que o cardápio manda servir na data, por turno e refeição. */
    private function sugestoesDoCardapio(int $escId, string $dt): array
    {
        $cardapio = Cardapio::query()
            ->vigenteParaEscola($escId, $dt)
            ->with('dias.prato:prt_id,prt_nome')
            ->first();

        if (! $cardapio) {
            return [];
        }

        $diaSemana = \Carbon\CarbonImmutable::parse($dt)->dayOfWeekIso;

        // Rodízio: qual semana do ciclo cai nesta data.
        $semanas = $cardapio->dias->pluck('cdi_semana')->unique()->sort()->values();
        $semana  = 1;

        if ($semanas->count() > 1) {
            $inicio    = \Carbon\CarbonImmutable::parse($cardapio->crd_dt_inicio)->startOfWeek();
            $decorrid  = (int) $inicio->diffInWeeks(\Carbon\CarbonImmutable::parse($dt)->startOfWeek());
            $semana    = $semanas[$decorrid % $semanas->count()];
        }

        return $cardapio->dias
            ->filter(fn ($d) => $d->cdi_dia_semana === $diaSemana && $d->cdi_semana === $semana)
            ->map(fn ($d) => [
                'prt_id'   => $d->cdi_prt_id,
                'prato'    => $d->prato?->prt_nome,
                'turno'    => $d->cdi_turno,
                'refeicao' => $d->cdi_refeicao,
            ])
            ->values()
            ->all();
    }

    /** Escola resumida para o EscolaCombobox exibir o nome do que já está escolhido. */
    private function escolaResumo(?int $escId): ?array
    {
        if (! $escId) {
            return null;
        }

        $escola = Escola::find($escId, ['esc_id', 'esc_nome']);

        return $escola ? ['esc_id' => $escola->esc_id, 'esc_nome' => $escola->esc_nome] : null;
    }

    private function baseQuery(Request $request, ?int $escId)
    {
        return Preparacao::query()
            ->when($escId !== null, fn ($q) => $q->where('prd_esc_id', $escId))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('prd_situacao', $s))
            ->when($request->input('turno'), fn ($q, $t) => $q->where('prd_turno', $t))
            ->when($request->input('dt_ini'), fn ($q, $d) => $q->whereDate('prd_dt', '>=', $d))
            ->when($request->input('dt_fim'), fn ($q, $d) => $q->whereDate('prd_dt', '<=', $d));
    }

    private function exportCsv($preparacoes): StreamedResponse
    {
        $filename = 'preparacoes_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($preparacoes) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Data', 'Escola', 'Turno', 'Refeição', 'Prato', 'Porções', 'Situação', 'Item', 'Previsto', 'Utilizado', 'Desvio', 'Unid.'], ';');

            foreach ($preparacoes as $p) {
                $cabecalho = [
                    $p->prd_dt->format('d/m/Y'),
                    $p->escola?->esc_nome ?? '',
                    $p->prd_turno,
                    $p->prd_refeicao,
                    $p->prato?->prt_nome ?? '',
                    $p->prd_qtd_servida,
                    $p->prd_situacao,
                ];

                if ($p->itens->isEmpty()) {
                    fputcsv($out, array_merge($cabecalho, ['SEM ITENS', '', '', '', '']), ';');
                    continue;
                }

                foreach ($p->itens as $i) {
                    fputcsv($out, array_merge($cabecalho, [
                        $i->item?->itm_nome ?? '',
                        $this->num($i->pdi_qtd_prevista),
                        $this->num($i->pdi_qtd_utilizada),
                        $this->num($i->desvio()),
                        $i->unidadeMedida?->umd_sigla ?? '',
                    ]), ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($preparacoes, Request $request, ?int $escId): HttpResponse
    {
        $collection = collect($preparacoes);
        $filename   = 'controle_diario_preparacao_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_preparacoes_pdf', [
            'preparacoes' => $collection,
            'escopo'      => $escId ? (Escola::find($escId)?->esc_nome ?? '') : 'Todas as escolas',
            'periodo'     => $this->periodoTexto($request),
            'total'       => $collection->count(),
            'porcoes'     => $collection->where('prd_situacao', Preparacao::SITUACAO_CONFIRMADA)->sum('prd_qtd_servida'),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function periodoTexto(Request $request): string
    {
        $ini = $request->input('dt_ini');
        $fim = $request->input('dt_fim');

        return match (true) {
            $ini && $fim => 'de '.$this->dataBr($ini).' a '.$this->dataBr($fim),
            (bool) $ini  => 'a partir de '.$this->dataBr($ini),
            (bool) $fim  => 'até '.$this->dataBr($fim),
            default      => 'todo o período',
        };
    }

    private function dataBr(string $iso): string
    {
        return implode('/', array_reverse(explode('-', substr($iso, 0, 10))));
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }
}
