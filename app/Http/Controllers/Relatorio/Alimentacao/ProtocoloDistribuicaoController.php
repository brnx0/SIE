<?php

namespace App\Http\Controllers\Relatorio\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Movimento;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Protocolo da Central de Distribuição.
 *
 * Lista o que a central entregou a cada unidade escolar no período, para a
 * escola conferir e assinar o recebimento.
 *
 * A fonte é a perna de ENTRADA da transferência (`MovimentaEstoque::transferir()`
 * grava saída na origem + entrada no destino, ligadas por `mov_trf_par_id`).
 * É a entrada que carrega a escola de destino em `mov_esc_id` — a saída fica no
 * central, com `mov_esc_id` nulo. Ler pela entrada evita ter que ir ao par só
 * para descobrir para quem o item foi.
 */
class ProtocoloDistribuicaoController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlimentacaoProtocolo/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'dt_ini' => ['required', 'date'],
            'dt_fim' => ['required', 'date', 'after_or_equal:dt_ini'],
        ], [], [
            'esc_id' => 'unidade escolar',
            'dt_ini' => 'data inicial',
            'dt_fim' => 'data final',
        ]);

        $user       = auth()->user();
        $escolasIds = UserAccess::escolasIds($user);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $movimentos = Movimento::query()
            ->where('mov_tipo', Movimento::TIPO_ENTRADA)
            // Entrada com par = perna de destino de uma transferência. Entrada de
            // recebimento de fornecedor não tem par, e não é distribuição.
            ->whereNotNull('mov_trf_par_id')
            ->whereNotNull('mov_esc_id')
            // Só o que saiu da central: transferência escola → escola existe no
            // modelo (devolução), mas não é protocolo da central.
            ->whereHas('parTransferencia', fn ($q) => $q->whereNull('mov_esc_id'))
            ->whereDate('mov_dt', '>=', $data['dt_ini'])
            ->whereDate('mov_dt', '<=', $data['dt_fim'])
            ->when(! empty($data['esc_id']), fn ($q) => $q->where('mov_esc_id', (int) $data['esc_id']))
            // Perfil com lotação só protocola a própria escola.
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('mov_esc_id', $escolasIds ?? []))
            ->with([
                'escola:esc_id,esc_nome',
                'item:itm_id,itm_nome,itm_cd_item,itm_umd_id',
                'item.unidadeMedida:umd_id,umd_sigla',
                'lote:lot_id,lot_cd_lote,lot_dt_validade,lot_mrc_id',
                'lote.marca:mrc_id,mrc_nome',
            ])
            ->orderBy('mov_esc_id')
            ->orderBy('mov_dt')
            ->orderBy('mov_id')
            ->get();

        // Um bloco por unidade: o protocolo é assinado pela escola, então cada
        // uma precisa da própria folha, com o próprio total.
        $unidades = $movimentos
            ->groupBy('mov_esc_id')
            ->map(fn ($doEstoque) => [
                'esc_id'   => $doEstoque->first()->mov_esc_id,
                'esc_nome' => $doEstoque->first()->escola?->esc_nome ?? '—',
                'linhas'   => $doEstoque->map(fn (Movimento $m) => [
                    'mov_id'        => $m->mov_id,
                    'dt'            => $m->mov_dt?->format('d/m/Y'),
                    'item_codigo'   => $m->item?->itm_cd_item,
                    'item_nome'     => $m->item?->itm_nome ?? '—',
                    'lote_codigo'   => $m->lote?->lot_cd_lote,
                    'lote_validade' => $m->lote?->lot_dt_validade?->format('d/m/Y'),
                    'marca'         => $m->lote?->marca?->mrc_nome,
                    'qtd'           => (float) $m->mov_qtd_base,
                    'umd_sigla'     => $m->item?->unidadeMedida?->umd_sigla,
                    'vl_total'      => $m->mov_vl_total !== null ? (float) $m->mov_vl_total : null,
                ])->values(),
                'total_linhas' => $doEstoque->count(),
                // Só o valor é somado. Quantidade não: o bloco mistura itens em KG,
                // L e UN, e somar isso daria um número sem significado.
                'valor_total'  => (float) $doEstoque->sum(fn (Movimento $m) => (float) $m->mov_vl_total),
            ])
            ->sortBy('esc_nome')
            ->values();

        return Inertia::render('relatorios/AlimentacaoProtocolo/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'esc_nome' => ! empty($data['esc_id'])
                    ? (Escola::find($data['esc_id'])?->esc_nome ?? '—')
                    : 'Todas as unidades',
                'dt_ini' => $this->dataBr($data['dt_ini']),
                'dt_fim' => $this->dataBr($data['dt_fim']),
            ],
            'unidades'    => $unidades,
            'valorGeral'  => (float) $movimentos->sum(fn (Movimento $m) => (float) $m->mov_vl_total),
            'totalLinhas' => $movimentos->count(),
        ]);
    }

    private function dataBr(string $iso): string
    {
        return implode('/', array_reverse(explode('-', substr($iso, 0, 10))));
    }
}
