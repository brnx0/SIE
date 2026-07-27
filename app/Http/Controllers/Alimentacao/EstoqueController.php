<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\CategoriaAlimento;
use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Movimento;
use App\Models\Escola\Escola;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta de estoque (saldo) e histórico de movimentação (razão).
 *
 * `esc_id` nulo = estoque central. Perfis com lotação (secretaria/diretor) só
 * enxergam o estoque da própria escola; almoxarife/nutricionista/admin veem tudo.
 */
class EstoqueController extends Controller
{
    /** Consulta de estoque: saldo por item e lote. */
    public function index(Request $request): Response
    {
        $escId   = $this->escolaEscolhida($request);
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 25;

        $saldos = $this->baseSaldo($request, $escId)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($s) => $this->linhaSaldo($s));

        return Inertia::render('alimentacao/estoque/Index', [
            'saldos'     => $saldos,
            // O EscolaCombobox busca em /api/escolas (já escopado por lotação);
            // aqui vai só a escola em exibição, para ele mostrar o nome.
            'escolaFiltro'   => $this->escolaResumo($escId),
            'podeVerCentral' => $this->podeVerCentral(),
            'categorias' => CategoriaAlimento::where('cat_fl_ativo', true)->orderBy('cat_nome')->get(['cat_id', 'cat_nome']),
            'resumo'     => $this->resumo($request, $escId),
            'filters'    => [
                'search'   => $request->input('search', ''),
                'esc_id'   => $request->input('esc_id', ''),
                'cat_id'   => $request->input('cat_id', ''),
                'alerta'   => $request->input('alerta', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Histórico de movimentação: o razão, que é a fonte da verdade do saldo. */
    public function movimentos(Request $request): Response
    {
        $escId   = $this->escolaEscolhida($request);
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 25;

        $movimentos = $this->baseMovimento($request, $escId)
            ->with([
                'item:itm_id,itm_nome,itm_umd_id',
                'item.unidadeMedida:umd_id,umd_sigla',
                'lote:lot_id,lot_cd_lote,lot_dt_validade',
                'unidadeMedida:umd_id,umd_sigla',
                'escola:esc_id,esc_nome',
                'fornecedor:frn_id,frn_razao_social',
                'tipoRetirada:tpr_id,tpr_nome',
                'usuario:id,name',
            ])
            ->orderByDesc('mov_dt')
            ->orderByDesc('mov_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($m) => $this->linhaMovimento($m));

        return Inertia::render('alimentacao/estoque/Movimentos', [
            'movimentos'     => $movimentos,
            'escolaFiltro'   => $this->escolaResumo($escId),
            'podeVerCentral' => $this->podeVerCentral(),
            'tipos'          => Movimento::TIPOS,
            'filters'        => [
                'search'   => $request->input('search', ''),
                'esc_id'   => $request->input('esc_id', ''),
                'tipo'     => $request->input('tipo', ''),
                'dt_ini'   => $request->input('dt_ini', ''),
                'dt_fim'   => $request->input('dt_fim', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $escId  = $this->escolaEscolhida($request);
        $saldos = $this->baseSaldo($request, $escId)->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($saldos, $request, $escId)
            : $this->exportCsv($saldos);
    }

    public function exportMovimentos(Request $request): StreamedResponse|HttpResponse
    {
        $escId = $this->escolaEscolhida($request);

        $movimentos = $this->baseMovimento($request, $escId)
            ->with(['item:itm_id,itm_nome,itm_umd_id', 'item.unidadeMedida:umd_id,umd_sigla',
                'lote:lot_id,lot_cd_lote,lot_dt_validade', 'unidadeMedida:umd_id,umd_sigla',
                'escola:esc_id,esc_nome', 'fornecedor:frn_id,frn_razao_social', 'tipoRetirada:tpr_id,tpr_nome', 'usuario:id,name'])
            ->orderByDesc('mov_dt')
            ->orderByDesc('mov_id')
            ->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportMovimentosPdf($movimentos, $request, $escId);
        }

        $filename = 'movimentos_estoque_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($movimentos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Data', 'Estoque', 'Tipo', 'Item', 'Lote', 'Validade', 'Qtd', 'Unid.', 'Qtd (base)', 'Custo unit.', 'Valor', 'Fornecedor', 'Tipo retirada', 'Usuário', 'Obs'], ';');

            foreach ($movimentos as $m) {
                fputcsv($out, [
                    $m->mov_dt->format('d/m/Y'),
                    $m->escola?->esc_nome ?? 'CENTRAL',
                    $m->mov_tipo,
                    $m->item?->itm_nome ?? '',
                    $m->lote?->lot_cd_lote ?? '',
                    $m->lote?->lot_dt_validade?->format('d/m/Y') ?? '',
                    $this->num($m->mov_qtd),
                    $m->unidadeMedida?->umd_sigla ?? '',
                    $this->num($m->mov_qtd_base),
                    $m->mov_vl_unitario !== null ? number_format((float) $m->mov_vl_unitario, 4, ',', '.') : '',
                    $m->mov_vl_total !== null ? number_format((float) $m->mov_vl_total, 2, ',', '.') : '',
                    $m->fornecedor?->frn_razao_social ?? '',
                    $m->tipoRetirada?->tpr_nome ?? '',
                    $m->usuario?->name ?? '',
                    $m->mov_obs ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    /**
     * Estoque que o usuário está vendo. Quem tem lotação fica preso à própria
     * escola: sem isso, a secretaria de uma escola veria (e depois mexeria no)
     * estoque de outra.
     */
    private function escolaEscolhida(Request $request): ?int
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        // null = perfil global (admin/almoxarife/nutricionista): escolhe livremente.
        if ($escolasIds === null) {
            $escId = $request->input('esc_id');

            return $escId === '' || $escId === null ? null : (int) $escId;
        }

        // Perfil com lotação: só a própria escola, e nunca o central.
        $pedida = (int) $request->input('esc_id');

        return in_array($pedida, $escolasIds, true) ? $pedida : ($escolasIds[0] ?? -1);
    }

    private function podeVerCentral(): bool
    {
        return UserAccess::escolasIds(request()->user()) === null;
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

    private function baseSaldo(Request $request, ?int $escId)
    {
        return EstoqueSaldo::query()
            ->doEstoque($escId)
            ->comSaldo()
            ->with([
                'item:itm_id,itm_nome,itm_cd_item,itm_umd_id,itm_cat_id,itm_estoque_minimo,itm_fl_perecivel',
                'item.unidadeMedida:umd_id,umd_sigla',
                'item.categoria:cat_id,cat_nome',
                'lote:lot_id,lot_cd_lote,lot_dt_validade,lot_mrc_id',
                'lote.marca:mrc_id,mrc_nome',
                'escola:esc_id,esc_nome',
            ])
            ->when($request->input('search'), fn ($q, $s) => $q->whereHas('item', fn ($i) => $i->search($s)))
            ->when($request->input('cat_id'), fn ($q, $id) => $q->whereHas('item', fn ($i) => $i->where('itm_cat_id', (int) $id)))
            // Vencidos e a vencer em 30 dias: é o filtro que a nutrição usa no dia a dia.
            ->when($request->input('alerta') === 'vencido',
                fn ($q) => $q->whereHas('lote', fn ($l) => $l->whereNotNull('lot_dt_validade')->whereDate('lot_dt_validade', '<', now())))
            ->when($request->input('alerta') === 'vencendo',
                fn ($q) => $q->whereHas('lote', fn ($l) => $l->whereNotNull('lot_dt_validade')
                    ->whereDate('lot_dt_validade', '>=', now())
                    ->whereDate('lot_dt_validade', '<=', now()->addDays(30))))
            ->join('ali_lote', 'ali_lote.lot_id', '=', 'ali_estoque_saldo.sld_lot_id')
            ->join('ali_item', 'ali_item.itm_id', '=', 'ali_estoque_saldo.sld_itm_id')
            ->orderBy('ali_item.itm_nome')
            ->orderByRaw('ali_lote.lot_dt_validade IS NULL')
            ->orderBy('ali_lote.lot_dt_validade')
            ->select('ali_estoque_saldo.*');
    }

    private function baseMovimento(Request $request, ?int $escId)
    {
        return Movimento::query()
            ->doEstoque($escId)
            ->when($request->input('search'), fn ($q, $s) => $q->whereHas('item', fn ($i) => $i->search($s)))
            ->when($request->input('tipo'), fn ($q, $t) => $q->where('mov_tipo', $t))
            ->when($request->input('dt_ini'), fn ($q, $d) => $q->whereDate('mov_dt', '>=', $d))
            ->when($request->input('dt_fim'), fn ($q, $d) => $q->whereDate('mov_dt', '<=', $d));
    }

    private function linhaSaldo(EstoqueSaldo $s): array
    {
        $minimo = $s->item?->itm_estoque_minimo !== null ? (float) $s->item->itm_estoque_minimo : null;

        return [
            'sld_id'        => $s->sld_id,
            'item_nome'     => $s->item?->itm_nome,
            'item_codigo'   => $s->item?->itm_cd_item,
            'categoria'     => $s->item?->categoria?->cat_nome,
            'umd_sigla'     => $s->item?->unidadeMedida?->umd_sigla,
            'lote_codigo'   => $s->lote?->lot_cd_lote,
            'lote_validade' => $s->lote?->lot_dt_validade?->toDateString(),
            'marca'         => $s->lote?->marca?->mrc_nome,
            'escola_nome'   => $s->escola?->esc_nome,
            'qtd'           => (float) $s->sld_qtd_base,
            'custo_medio'   => (float) $s->sld_vl_custo_medio,
            'valor_total'   => $s->valorTotal(),
            'dias_vencer'   => $s->lote?->diasParaVencer(),
            'vencido'       => (bool) $s->lote?->vencido(),
            // O mínimo é do item, mas o saldo é por lote: comparar lote a lote
            // acusaria falso alerta. A comparação certa é contra o total do item.
            'estoque_minimo' => $minimo,
        ];
    }

    private function linhaMovimento(Movimento $m): array
    {
        return [
            'mov_id'          => $m->mov_id,
            'mov_dt'          => $m->mov_dt?->toDateString(),
            'mov_tipo'        => $m->mov_tipo,
            'escola_nome'     => $m->escola?->esc_nome,
            'item_nome'       => $m->item?->itm_nome,
            'lote_codigo'     => $m->lote?->lot_cd_lote,
            'lote_validade'   => $m->lote?->lot_dt_validade?->toDateString(),
            'qtd'             => (float) $m->mov_qtd,
            'umd_sigla'       => $m->unidadeMedida?->umd_sigla,
            'qtd_base'        => (float) $m->mov_qtd_base,
            'umd_base'        => $m->item?->unidadeMedida?->umd_sigla,
            'vl_unitario'     => $m->mov_vl_unitario !== null ? (float) $m->mov_vl_unitario : null,
            'vl_total'        => $m->mov_vl_total !== null ? (float) $m->mov_vl_total : null,
            'fornecedor_nome' => $m->fornecedor?->frn_razao_social,
            'tipo_retirada'   => $m->tipoRetirada?->tpr_nome,
            'usuario_nome'    => $m->usuario?->name,
            'mov_obs'         => $m->mov_obs,
            'sinal'           => $m->sinal(),
            'transferencia'   => $m->mov_trf_par_id !== null,
            'transferencia_id' => $m->mov_transferencia_id,
            'estorno_de'      => $m->mov_estorno_de_id,
        ];
    }

    private function resumo(Request $request, ?int $escId): array
    {
        $base = EstoqueSaldo::query()->doEstoque($escId)->comSaldo();

        return [
            'itens'       => (clone $base)->distinct('sld_itm_id')->count('sld_itm_id'),
            'valor_total' => (float) (clone $base)->sum(DB::raw('sld_qtd_base * sld_vl_custo_medio')),
            'vencidos'    => (clone $base)->whereHas('lote', fn ($l) => $l->whereNotNull('lot_dt_validade')->whereDate('lot_dt_validade', '<', now()))->count(),
            'vencendo'    => (clone $base)->whereHas('lote', fn ($l) => $l->whereNotNull('lot_dt_validade')
                ->whereDate('lot_dt_validade', '>=', now())
                ->whereDate('lot_dt_validade', '<=', now()->addDays(30)))->count(),
        ];
    }

    private function exportCsv($saldos): StreamedResponse
    {
        $filename = 'estoque_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($saldos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Estoque', 'Código', 'Item', 'Categoria', 'Lote', 'Marca', 'Validade', 'Saldo', 'Unid.', 'Custo médio', 'Valor total'], ';');

            foreach ($saldos as $s) {
                fputcsv($out, [
                    $s->escola?->esc_nome ?? 'CENTRAL',
                    $s->item?->itm_cd_item ?? '',
                    $s->item?->itm_nome ?? '',
                    $s->item?->categoria?->cat_nome ?? '',
                    $s->lote?->lot_cd_lote ?? '',
                    $s->lote?->marca?->mrc_nome ?? '',
                    $s->lote?->lot_dt_validade?->format('d/m/Y') ?? '',
                    $this->num($s->sld_qtd_base),
                    $s->item?->unidadeMedida?->umd_sigla ?? '',
                    number_format((float) $s->sld_vl_custo_medio, 4, ',', '.'),
                    number_format($s->valorTotal(), 2, ',', '.'),
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Relatório de movimentação: entradas, saídas e retiradas do período. */
    private function exportMovimentosPdf($movimentos, Request $request, ?int $escId): HttpResponse
    {
        $collection = collect($movimentos);
        $filename   = 'movimentos_estoque_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_movimentos_pdf', [
            'movimentos' => $collection,
            'escopo'     => $escId === null ? 'Estoque central' : (Escola::find($escId)?->esc_nome ?? ''),
            'periodo'    => $this->periodoTexto($request),
            'total'      => $collection->count(),
            // Contagem, não soma de quantidade: o relatório mistura itens de
            // unidades diferentes, e somar KG com UN daria número sem sentido.
            'qtdEntradas' => $collection->where('mov_tipo', Movimento::TIPO_ENTRADA)->count(),
            'qtdSaidas'   => $collection->filter(fn ($m) => $m->sinal() === -1)->count(),
            // Valor é somável: é tudo real.
            'valorEntradas' => $collection->where('mov_tipo', Movimento::TIPO_ENTRADA)->sum(fn ($m) => (float) $m->mov_vl_total),
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

    private function exportPdf($saldos, Request $request, ?int $escId): HttpResponse
    {
        $collection = collect($saldos);
        $filename   = 'estoque_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_estoque_pdf', [
            'saldos'     => $collection,
            'escopo'     => $escId === null ? 'Estoque central' : (Escola::find($escId)?->esc_nome ?? ''),
            'total'      => $collection->count(),
            'valorTotal' => $collection->sum(fn ($s) => $s->valorTotal()),
            'search'     => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }
}
