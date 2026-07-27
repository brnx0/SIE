<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Demanda;
use App\Models\Alimentacao\DemandaItem;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Services\Alimentacao\CalculadoraDemanda;
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
 * Demanda mensal de merenda por unidade.
 *
 * O cálculo sai do cardápio × calendário letivo × alunos por turno. O
 * nutricionista pode ajustar item a item; o calculado nunca é sobrescrito,
 * para o comparativo continuar existindo.
 */
class DemandaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $demandas = $this->baseQuery($request)
            ->with(['escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano', 'itens'])
            ->orderByDesc('dmd_ano')
            ->orderByDesc('dmd_mes')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($d) => [
                'dmd_id'           => $d->dmd_id,
                'dmd_mes'          => $d->dmd_mes,
                'dmd_ano'          => $d->dmd_ano,
                'mes_nome'         => $d->nomeMes(),
                'dmd_situacao'     => $d->dmd_situacao,
                'dmd_dias_letivos' => $d->dmd_dias_letivos,
                'escola_nome'      => $d->escola?->esc_nome,
                'ano_letivo'       => $d->anoLetivo?->anl_ano,
                'itens_count'      => $d->itens->count(),
                'ajustados'        => $d->itens->filter(fn ($i) => $i->ajustada())->count(),
            ]);

        return Inertia::render('alimentacao/demandas/Index', [
            'demandas'    => $demandas,
            // O EscolaCombobox busca em /api/escolas (já escopado por lotação);
            // aqui vai só a escola do filtro atual, para ele exibir o nome.
            'escolaFiltro' => $this->escolaResumo($request->input('esc_id') ? (int) $request->input('esc_id') : null),
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'situacoes'   => Demanda::SITUACOES,
            'meses'       => Demanda::MESES,
            'filters'     => [
                'esc_id'   => $request->input('esc_id', ''),
                'anl_id'   => $request->input('anl_id', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        // Perfil com lotação já vem com a própria escola preenchida; perfil
        // global escolhe pelo combobox.
        $escolasIds = UserAccess::escolasIds($request->user());

        return Inertia::render('alimentacao/demandas/Create', [
            'escolaInicial' => $this->escolaResumo($escolasIds === null ? null : ($escolasIds[0] ?? null)),
            'anosLetivos'   => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'meses'         => Demanda::MESES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            [
                'dmd_esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
                'dmd_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
                'dmd_mes'    => ['required', 'integer', 'between:1,12'],
                'dmd_ano'    => ['required', 'integer', 'min:2000', 'max:2099'],
                'dmd_obs'    => ['nullable', 'string'],
            ],
            [],
            [
                'dmd_esc_id' => 'escola',
                'dmd_anl_id' => 'ano letivo',
                'dmd_mes'    => 'mês',
                'dmd_ano'    => 'ano',
            ],
        );

        $this->assertEscolaPermitida($request, (int) $dados['dmd_esc_id']);
        $this->assertPeriodoLivre($dados);

        $demanda = Demanda::create($dados + [
            'dmd_situacao' => Demanda::SITUACAO_RASCUNHO,
            'dmd_usr_id'   => $request->user()?->id,
        ]);

        // Já calcula: uma demanda vazia não serve para nada, e o erro do cálculo
        // (sem cardápio, sem aluno) precisa aparecer agora, não depois.
        try {
            app(CalculadoraDemanda::class)->calcular($demanda);
        } catch (ValidationException $e) {
            $demanda->forceDelete();
            throw $e;
        }

        return to_route('alimentacao.demandas.edit', $demanda->dmd_id)
            ->with('success', 'Demanda calculada a partir do cardápio e do calendário letivo.');
    }

    public function edit(int $id): Response
    {
        $demanda = Demanda::with([
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'cardapio:crd_id,crd_nome,crd_esc_id',
            'itens.item:itm_id,itm_nome,itm_cd_item',
            'itens.unidadeMedida:umd_id,umd_sigla',
        ])->findOrFail($id);

        $this->assertPodeVer($demanda);

        return Inertia::render('alimentacao/demandas/Edit', [
            'demanda' => [
                'dmd_id'           => $demanda->dmd_id,
                'dmd_mes'          => $demanda->dmd_mes,
                'dmd_ano'          => $demanda->dmd_ano,
                'mes_nome'         => $demanda->nomeMes(),
                'dmd_situacao'     => $demanda->dmd_situacao,
                'dmd_dias_letivos' => $demanda->dmd_dias_letivos,
                'dmd_obs'          => $demanda->dmd_obs,
                'escola_nome'      => $demanda->escola?->esc_nome,
                'ano_letivo'       => $demanda->anoLetivo?->anl_ano,
                'cardapio_nome'    => $demanda->cardapio?->crd_nome,
                'cardapio_da_rede' => $demanda->cardapio ? $demanda->cardapio->crd_esc_id === null : null,
            ],
            'itens' => $demanda->itens->map(fn ($i) => [
                'dmi_id'        => $i->dmi_id,
                'item_nome'     => $i->item?->itm_nome,
                'item_codigo'   => $i->item?->itm_cd_item,
                'umd_sigla'     => $i->unidadeMedida?->umd_sigla,
                'qtd_calculada' => (float) $i->dmi_qtd_calculada,
                'qtd_ajustada'  => $i->dmi_qtd_ajustada !== null ? (float) $i->dmi_qtd_ajustada : null,
                'qtd_final'     => $i->qtdFinal(),
                'justificativa' => $i->dmi_justificativa,
                'ajustada'      => $i->ajustada(),
            ])->sortBy('item_nome')->values(),
            'emRascunho' => $demanda->emRascunho(),
        ]);
    }

    /** Recalcula do zero a partir do cardápio — perde os ajustes manuais. */
    public function calcular(int $id): RedirectResponse
    {
        $demanda = Demanda::findOrFail($id);
        $this->assertPodeVer($demanda);

        app(CalculadoraDemanda::class)->calcular($demanda);

        return back()->with('success', 'Demanda recalculada a partir do cardápio e do calendário letivo.');
    }

    /** Ajuste manual de um item. O calculado permanece, para o comparativo. */
    public function ajustarItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        $demanda = Demanda::findOrFail($id);
        $this->assertPodeVer($demanda);
        $this->assertEmRascunho($demanda);

        $linha = DemandaItem::where('dmi_dmd_id', $demanda->dmd_id)->findOrFail($itemId);

        $dados = $request->validate(
            [
                'dmi_qtd_ajustada'  => ['nullable', 'numeric', 'min:0'],
                'dmi_justificativa' => ['nullable', 'string', 'required_with:dmi_qtd_ajustada'],
            ],
            [
                'dmi_justificativa.required_with' => 'Explique por que a quantidade está sendo ajustada — é isso que sustenta a compra fora do cálculo.',
            ],
            [
                'dmi_qtd_ajustada'  => 'quantidade ajustada',
                'dmi_justificativa' => 'justificativa',
            ],
        );

        $linha->update([
            'dmi_qtd_ajustada'  => $dados['dmi_qtd_ajustada'] ?? null,
            'dmi_justificativa' => $dados['dmi_qtd_ajustada'] !== null ? ($dados['dmi_justificativa'] ?? null) : null,
        ]);

        return back()->with('success', $dados['dmi_qtd_ajustada'] !== null ? 'Ajuste registrado.' : 'Ajuste removido — voltou ao valor calculado.');
    }

    public function fechar(int $id): RedirectResponse
    {
        $demanda = Demanda::findOrFail($id);
        $this->assertPodeVer($demanda);

        if (! $demanda->emRascunho()) {
            return back()->with('error', 'Esta demanda já está fechada.');
        }

        if ($demanda->itens()->count() === 0) {
            return back()->with('error', 'Não é possível fechar uma demanda sem itens. Recalcule antes.');
        }

        $demanda->update(['dmd_situacao' => Demanda::SITUACAO_FECHADA]);

        return back()->with('success', 'Demanda fechada. Ela vira a base da compra e não é mais recalculada.');
    }

    public function reabrir(int $id): RedirectResponse
    {
        $demanda = Demanda::findOrFail($id);
        $this->assertPodeVer($demanda);

        $demanda->update(['dmd_situacao' => Demanda::SITUACAO_RASCUNHO]);

        return back()->with('success', 'Demanda reaberta para ajuste.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $demanda = Demanda::findOrFail($id);
        $this->assertPodeVer($demanda);

        if (! $demanda->emRascunho()) {
            return back()->with('error', 'Demanda fechada não pode ser excluída. Reabra-a antes.');
        }

        $demanda->itens()->delete();

        return $this->safeDelete($demanda)
            ?? to_route('alimentacao.demandas.index')->with('success', 'Demanda removida.');
    }

    public function export(Request $request, int $id): StreamedResponse|HttpResponse
    {
        $demanda = Demanda::with([
            'escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano', 'cardapio:crd_id,crd_nome',
            'itens.item:itm_id,itm_nome,itm_cd_item', 'itens.unidadeMedida:umd_id,umd_sigla',
        ])->findOrFail($id);

        $this->assertPodeVer($demanda);

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($demanda)
            : $this->exportCsv($demanda);
    }

    // ------------------------------------------------------------------

    private function assertEscolaPermitida(Request $request, int $escId): void
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        if ($escolasIds === null || in_array($escId, $escolasIds, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'dmd_esc_id' => 'Você só pode montar a demanda da sua escola.',
        ]);
    }

    private function assertPodeVer(Demanda $demanda): void
    {
        $escolasIds = UserAccess::escolasIds(request()->user());

        if ($escolasIds === null || in_array($demanda->dmd_esc_id, $escolasIds, true)) {
            return;
        }

        abort(403, 'Esta demanda é de outra escola.');
    }

    private function assertEmRascunho(Demanda $demanda): void
    {
        if ($demanda->emRascunho()) {
            return;
        }

        throw ValidationException::withMessages([
            'dmi_qtd_ajustada' => 'Esta demanda está fechada. Reabra-a para ajustar os itens.',
        ]);
    }

    /** Duas demandas do mesmo mês dariam números conflitantes para a mesma compra. */
    private function assertPeriodoLivre(array $dados): void
    {
        $existe = Demanda::query()
            ->where('dmd_esc_id', $dados['dmd_esc_id'])
            ->where('dmd_ano', $dados['dmd_ano'])
            ->where('dmd_mes', $dados['dmd_mes'])
            ->exists();

        if (! $existe) {
            return;
        }

        throw ValidationException::withMessages([
            'dmd_mes' => 'Já existe demanda desta escola para '
                .(Demanda::MESES[$dados['dmd_mes']] ?? '').'/'.$dados['dmd_ano'].'. Edite a que existe.',
        ]);
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

    private function baseQuery(Request $request)
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        return Demanda::query()
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('dmd_esc_id', $escolasIds ?: [-1]))
            ->when($request->input('esc_id'), fn ($q, $id) => $q->where('dmd_esc_id', (int) $id))
            ->when($request->input('anl_id'), fn ($q, $id) => $q->where('dmd_anl_id', (int) $id))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('dmd_situacao', $s));
    }

    private function exportCsv(Demanda $demanda): StreamedResponse
    {
        $filename = 'demanda_'.$demanda->dmd_ano.'_'.str_pad((string) $demanda->dmd_mes, 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use ($demanda) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Código', 'Item', 'Unid.', 'Calculado', 'Ajustado', 'A comprar', 'Justificativa'], ';');

            foreach ($demanda->itens->sortBy(fn ($i) => $i->item?->itm_nome) as $i) {
                fputcsv($out, [
                    $i->item?->itm_cd_item ?? '',
                    $i->item?->itm_nome ?? '',
                    $i->unidadeMedida?->umd_sigla ?? '',
                    $this->num($i->dmi_qtd_calculada),
                    $i->dmi_qtd_ajustada !== null ? $this->num($i->dmi_qtd_ajustada) : '',
                    $this->num($i->qtdFinal()),
                    $i->dmi_justificativa ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf(Demanda $demanda): HttpResponse
    {
        $filename = 'demanda_'.$demanda->dmd_ano.'_'.str_pad((string) $demanda->dmd_mes, 2, '0', STR_PAD_LEFT).'.pdf';

        $html = view('exports.alimentacao_demanda_pdf', [
            'demanda' => $demanda,
            'itens'   => $demanda->itens->sortBy(fn ($i) => $i->item?->itm_nome),
        ])->render();

        $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
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
