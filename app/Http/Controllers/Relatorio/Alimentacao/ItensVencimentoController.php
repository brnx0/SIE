<?php

namespace App\Http\Controllers\Relatorio\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Itens Próximos do Vencimento.
 *
 * Saldo cujo lote vence dentro do prazo informado, para a nutrição agir antes de
 * perder o item (remanejar, priorizar no cardápio ou retirar por vencimento).
 *
 * O prazo é do usuário, não fixo: a Consulta de Estoque já tem o alerta de 30
 * dias embutido, e o ponto deste relatório é justamente poder pedir outro
 * horizonte (7 dias para a semana, 60 para planejar o cardápio).
 */
class ItensVencimentoController extends Controller
{
    /** Escopos de estoque que o relatório aceita. */
    private const ESCOPOS = ['central', 'escola', 'todos'];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlimentacaoVencimento/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            // Quem tem lotação não escolhe escopo: vê só o estoque da própria
            // escola, nunca o central nem o de outra unidade.
            'podeVerCentral' => UserAccess::escolasIds($user) === null,
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'escopo'           => ['required', Rule::in(self::ESCOPOS)],
            'esc_id'           => ['nullable', 'integer', 'exists:edu_escola,esc_id', 'required_if:escopo,escola'],
            'dias'             => ['required', 'integer', 'min:1', 'max:365'],
            'incluir_vencidos' => ['boolean'],
        ], [], [
            'escopo' => 'estoque',
            'esc_id' => 'unidade escolar',
            'dias'   => 'prazo em dias',
        ]);

        $user       = auth()->user();
        $escolasIds = UserAccess::escolasIds($user);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $incluirVencidos = $request->boolean('incluir_vencidos');
        $limite          = now()->addDays((int) $data['dias'])->toDateString();

        // Perfil com lotação cai à força na própria escola, seja qual for o
        // escopo pedido: a tela não oferece a opção, mas o request é do usuário.
        $escopo = $escolasIds === null ? $data['escopo'] : 'escola';
        $escId  = $escolasIds === null
            ? (! empty($data['esc_id']) ? (int) $data['esc_id'] : null)
            : ($escolasIds[0] ?? -1);

        $saldos = EstoqueSaldo::query()
            ->comSaldo()
            ->when($escopo === 'central', fn ($q) => $q->whereNull('sld_esc_id'))
            ->when($escopo === 'escola', fn ($q) => $q->where('sld_esc_id', $escId))
            ->whereHas('lote', function ($l) use ($limite, $incluirVencidos) {
                // Lote sem validade nunca entra: não há o que vencer.
                $l->whereNotNull('lot_dt_validade')->whereDate('lot_dt_validade', '<=', $limite);

                if (! $incluirVencidos) {
                    $l->whereDate('lot_dt_validade', '>=', now()->toDateString());
                }
            })
            ->with([
                'item:itm_id,itm_nome,itm_cd_item,itm_umd_id,itm_cat_id',
                'item.unidadeMedida:umd_id,umd_sigla',
                'item.categoria:cat_id,cat_nome',
                'lote:lot_id,lot_cd_lote,lot_dt_validade,lot_mrc_id',
                'lote.marca:mrc_id,mrc_nome',
                'escola:esc_id,esc_nome',
            ])
            ->join('ali_lote', 'ali_lote.lot_id', '=', 'ali_estoque_saldo.sld_lot_id')
            ->orderBy('ali_lote.lot_dt_validade')
            ->select('ali_estoque_saldo.*')
            ->get();

        $linhas = $saldos->map(fn (EstoqueSaldo $s) => [
            'sld_id'        => $s->sld_id,
            'estoque'       => $s->escola?->esc_nome ?? 'CENTRAL',
            'item_codigo'   => $s->item?->itm_cd_item,
            'item_nome'     => $s->item?->itm_nome ?? '—',
            'categoria'     => $s->item?->categoria?->cat_nome,
            'lote_codigo'   => $s->lote?->lot_cd_lote,
            'marca'         => $s->lote?->marca?->mrc_nome,
            'lote_validade' => $s->lote?->lot_dt_validade?->format('d/m/Y'),
            'dias_vencer'   => $s->lote?->diasParaVencer(),
            'vencido'       => (bool) $s->lote?->vencido(),
            'qtd'           => (float) $s->sld_qtd_base,
            'umd_sigla'     => $s->item?->unidadeMedida?->umd_sigla,
            'valor_total'   => $s->valorTotal(),
        ])->values();

        return Inertia::render('relatorios/AlimentacaoVencimento/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'escopo_nome' => match ($escopo) {
                    'central' => 'Estoque central',
                    'todos'   => 'Todos os estoques',
                    default   => Escola::find($escId)?->esc_nome ?? '—',
                },
                'dias'             => (int) $data['dias'],
                'incluir_vencidos' => $incluirVencidos,
            ],
            'linhas'       => $linhas,
            'total'        => $linhas->count(),
            'totalVencido' => $linhas->where('vencido', true)->count(),
            'valorTotal'   => (float) $saldos->sum(fn (EstoqueSaldo $s) => $s->valorTotal()),
        ]);
    }
}
