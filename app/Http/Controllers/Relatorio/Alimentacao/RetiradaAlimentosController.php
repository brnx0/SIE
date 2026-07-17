<?php

namespace App\Http\Controllers\Relatorio\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Movimento;
use App\Models\Alimentacao\TipoRetirada;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Retirada de Alimentos — relatório e gráfico comparativo.
 *
 * Retirada é a saída do estoque da unidade classificada por motivo (vencimento,
 * avaria, descarte, devolução ao central...). O comparativo entre unidades é o
 * que mostra onde está se perdendo alimento.
 *
 * Os agregados são em R$, nunca em quantidade: o relatório cruza itens medidos
 * em KG, L e UN, e somar essas quantidades daria um número sem significado. O
 * valor sai do custo médio do saldo no momento da baixa (`MovimentaEstoque`).
 */
class RetiradaAlimentosController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlimentacaoRetirada/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            'tipos'      => TipoRetirada::where('tpr_fl_ativo', true)
                ->orderBy('tpr_nome')
                ->get(['tpr_id', 'tpr_nome']),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tpr_id' => ['nullable', 'integer', 'exists:ali_tipo_retirada,tpr_id'],
            'dt_ini' => ['required', 'date'],
            'dt_fim' => ['required', 'date', 'after_or_equal:dt_ini'],
        ], [], [
            'esc_id' => 'unidade escolar',
            'tpr_id' => 'motivo da retirada',
            'dt_ini' => 'data inicial',
            'dt_fim' => 'data final',
        ]);

        $user       = auth()->user();
        $escolasIds = UserAccess::escolasIds($user);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $movimentos = Movimento::query()
            ->where('mov_tipo', Movimento::TIPO_RETIRADA)
            ->whereDate('mov_dt', '>=', $data['dt_ini'])
            ->whereDate('mov_dt', '<=', $data['dt_fim'])
            ->when(! empty($data['esc_id']), fn ($q) => $q->where('mov_esc_id', (int) $data['esc_id']))
            ->when(! empty($data['tpr_id']), fn ($q) => $q->where('mov_tpr_id', (int) $data['tpr_id']))
            // Perfil com lotação vê só as retiradas da própria escola.
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('mov_esc_id', $escolasIds ?? []))
            ->with([
                'escola:esc_id,esc_nome',
                'item:itm_id,itm_nome,itm_cd_item,itm_umd_id',
                'item.unidadeMedida:umd_id,umd_sigla',
                'lote:lot_id,lot_cd_lote,lot_dt_validade',
                'tipoRetirada:tpr_id,tpr_nome',
                'funcionario:fun_id,fun_nome',
                'usuario:id,name',
            ])
            ->orderBy('mov_dt')
            ->orderBy('mov_id')
            ->get();

        $linhas = $movimentos->map(fn (Movimento $m) => [
            'mov_id'        => $m->mov_id,
            'dt'            => $m->mov_dt?->format('d/m/Y'),
            'estoque'       => $m->escola?->esc_nome ?? 'CENTRAL',
            'item_nome'     => $m->item?->itm_nome ?? '—',
            'lote_codigo'   => $m->lote?->lot_cd_lote,
            'lote_validade' => $m->lote?->lot_dt_validade?->format('d/m/Y'),
            'qtd'           => (float) $m->mov_qtd_base,
            'umd_sigla'     => $m->item?->unidadeMedida?->umd_sigla,
            'tipo_retirada' => $m->tipoRetirada?->tpr_nome ?? '—',
            'responsavel'   => $m->funcionario?->fun_nome ?? $m->usuario?->name,
            'vl_total'      => $m->mov_vl_total !== null ? (float) $m->mov_vl_total : null,
            'mov_obs'       => $m->mov_obs,
        ])->values();

        return Inertia::render('relatorios/AlimentacaoRetirada/Resultado', [
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
                'tpr_nome' => ! empty($data['tpr_id'])
                    ? (TipoRetirada::find($data['tpr_id'])?->tpr_nome ?? '—')
                    : 'Todos os motivos',
                'dt_ini' => $this->dataBr($data['dt_ini']),
                'dt_fim' => $this->dataBr($data['dt_fim']),
            ],
            'linhas'     => $linhas,
            'porUnidade' => $this->agrupar($movimentos, fn (Movimento $m) => $m->escola?->esc_nome ?? 'CENTRAL'),
            'porTipo'    => $this->agrupar($movimentos, fn (Movimento $m) => $m->tipoRetirada?->tpr_nome ?? 'Sem motivo'),
            'total'      => $linhas->count(),
            'valorTotal' => (float) $movimentos->sum(fn (Movimento $m) => (float) $m->mov_vl_total),
        ]);
    }

    /**
     * Agrega o valor retirado por uma chave (unidade ou motivo), do maior para o
     * menor — é a ordem em que o gráfico e a tabela comparativa são lidos.
     *
     * @param  \Illuminate\Support\Collection<int, Movimento>  $movimentos
     */
    private function agrupar($movimentos, callable $chave): array
    {
        return $movimentos
            ->groupBy($chave)
            ->map(fn ($grupo, $nome) => [
                'nome'   => (string) $nome,
                'linhas' => $grupo->count(),
                'valor'  => (float) $grupo->sum(fn (Movimento $m) => (float) $m->mov_vl_total),
            ])
            ->sortByDesc('valor')
            ->values()
            ->all();
    }

    private function dataBr(string $iso): string
    {
        return implode('/', array_reverse(explode('-', substr($iso, 0, 10))));
    }
}
