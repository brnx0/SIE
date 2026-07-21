<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Concerns\ComAnexos;
use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Anexo;
use App\Models\Alimentacao\Pergunta;
use App\Models\Alimentacao\TipoQuestionario;
use App\Models\Alimentacao\TipoVisita;
use App\Models\Alimentacao\Visita;
use App\Models\Alimentacao\VisitaResposta;
use App\Models\Escola\Escola;
use App\Services\Alimentacao\AplicaQuestionario;
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
 * Visitas técnicas: programação, aplicação do questionário e ateste.
 */
class VisitaController extends Controller
{
    use ComAnexos;

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $visitas = $this->baseQuery($request)
            ->with([
                'escola:esc_id,esc_nome',
                'tipoVisita:tpv_id,tpv_nome',
                'tecnico:fun_id,fun_nome',
                'atestador:fun_id,fun_nome',
                'respostas',
            ])
            ->orderByDesc('vis_dt_programada')
            ->orderByDesc('vis_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($v) => [
                'vis_id'            => $v->vis_id,
                'vis_dt_programada' => $v->vis_dt_programada?->toDateString(),
                'vis_dt_realizada'  => $v->vis_dt_realizada?->toDateString(),
                'vis_dt_ateste'     => $v->vis_dt_ateste?->toDateString(),
                'vis_situacao'      => $v->vis_situacao,
                'escola_nome'       => $v->escola?->esc_nome,
                'tipo_visita'       => $v->tipoVisita?->tpv_nome,
                'tecnico_nome'      => $v->tecnico?->fun_nome,
                'atestador_nome'    => $v->atestador?->fun_nome,
                'respostas_count'   => $v->respostas->count(),
                'respondidas'       => $v->respostas->filter(fn ($r) => $r->respondida())->count(),
            ]);

        return Inertia::render('alimentacao/visitas/Index', [
            'visitas'      => $visitas,
            'escolaFiltro' => $this->escolaResumo($request->input('esc_id') ? (int) $request->input('esc_id') : null),
            'tiposVisita'  => TipoVisita::where('tpv_fl_ativo', true)->orderBy('tpv_nome')->get(['tpv_id', 'tpv_nome']),
            'situacoes'    => Visita::SITUACOES,
            'filters'      => [
                'search'   => $request->input('search', ''),
                'esc_id'   => $request->input('esc_id', ''),
                'tpv_id'   => $request->input('tpv_id', ''),
                'situacao' => $request->input('situacao', ''),
                'dt_ini'   => $request->input('dt_ini', ''),
                'dt_fim'   => $request->input('dt_fim', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        return Inertia::render('alimentacao/visitas/Create', [
            'escolaInicial' => $this->escolaResumo($escolasIds === null ? null : ($escolasIds[0] ?? null)),
            'tiposVisita'   => TipoVisita::where('tpv_fl_ativo', true)->orderBy('tpv_nome')->get(['tpv_id', 'tpv_nome']),
            'questionarios' => $this->questionariosComPergunta(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            [
                'vis_esc_id'        => ['required', 'integer', 'exists:edu_escola,esc_id'],
                'vis_tpv_id'        => ['required', 'integer', 'exists:ali_tipo_visita,tpv_id'],
                'vis_tpq_id'        => ['required', 'integer', 'exists:ali_tipo_questionario,tpq_id'],
                'vis_fun_id'        => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
                'vis_dt_programada' => ['required', 'date'],
                'vis_obs'           => ['nullable', 'string'],
            ],
            [],
            [
                'vis_esc_id'        => 'escola',
                'vis_tpv_id'        => 'tipo de visita',
                'vis_tpq_id'        => 'questionário',
                'vis_fun_id'        => 'técnico responsável',
                'vis_dt_programada' => 'data programada',
            ],
        );

        $this->assertEscolaPermitida($request, (int) $dados['vis_esc_id']);

        $visita = Visita::create($dados + [
            'vis_situacao' => Visita::SITUACAO_PROGRAMADA,
            'vis_usr_id'   => $request->user()?->id,
        ]);

        // Monta o questionário já: erro (questionário sem pergunta) precisa
        // aparecer agora, e não na hora de aplicar na escola.
        try {
            app(AplicaQuestionario::class)->montarRespostas($visita);
        } catch (ValidationException $e) {
            $visita->forceDelete();
            throw $e;
        }

        return to_route('alimentacao.visitas.edit', $visita->vis_id)
            ->with('success', 'Visita programada. O questionário já está pronto para ser respondido.');
    }

    public function edit(int $id): Response
    {
        $visita = Visita::with([
            'escola:esc_id,esc_nome',
            'tipoVisita:tpv_id,tpv_nome',
            'tipoQuestionario:tpq_id,tpq_nome',
            'tecnico:fun_id,fun_nome',
            'atestador:fun_id,fun_nome',
            'respostas.pergunta.opcoes',
            'respostas.opcao',
        ])->findOrFail($id);

        $this->assertPodeVer($visita);

        return Inertia::render('alimentacao/visitas/Edit', [
            'visita' => [
                'vis_id'            => $visita->vis_id,
                'vis_dt_programada' => $visita->vis_dt_programada?->toDateString(),
                'vis_dt_realizada'  => $visita->vis_dt_realizada?->toDateString(),
                'vis_dt_ateste'     => $visita->vis_dt_ateste?->toDateString(),
                'vis_situacao'      => $visita->vis_situacao,
                'vis_parecer'       => $visita->vis_parecer,
                'vis_obs'           => $visita->vis_obs,
                'vis_motivo_cancelamento' => $visita->vis_motivo_cancelamento,
                'vis_fun_id'        => $visita->vis_fun_id,
                'escola_nome'       => $visita->escola?->esc_nome,
                'tipo_visita'       => $visita->tipoVisita?->tpv_nome,
                'questionario_nome' => $visita->tipoQuestionario?->tpq_nome,
                'tecnico_nome'      => $visita->tecnico?->fun_nome,
                'atestador_nome'    => $visita->atestador?->fun_nome,
            ],
            'respostas' => $visita->respostas
                ->sortBy(fn ($r) => [$r->pergunta?->prg_ordem ?? 999, $r->vre_prg_id])
                ->values()
                ->map(fn ($r) => [
                    'vre_id'       => $r->vre_id,
                    // Texto e tipo vêm congelados da resposta, não da pergunta atual:
                    // é o que a visita realmente perguntou naquele dia.
                    'pergunta'     => $r->vre_pergunta_texto,
                    'tipo'         => $r->vre_tipo_resposta,
                    'obrigatoria'  => (bool) $r->pergunta?->prg_fl_obrigatoria,
                    'resposta'     => $r->vre_resposta,
                    'pro_id'       => $r->vre_pro_id,
                    'obs'          => $r->vre_obs,
                    'respondida'   => $r->respondida(),
                    'texto_resposta' => $r->textoResposta(),
                    'opcoes'       => $r->pergunta?->opcoes->sortBy('pro_ordem')->map(fn ($o) => [
                        'pro_id'    => $o->pro_id,
                        'pro_texto' => $o->pro_texto,
                    ])->values() ?? [],
                ]),
            'editavel'    => $visita->editavel(),
            'podeAtestar' => $visita->podeAtestar(),
            'anexos'      => $this->anexosPara($visita),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);
        $this->assertEditavel($visita);

        $dados = $request->validate(
            [
                'vis_tpv_id'        => ['required', 'integer', 'exists:ali_tipo_visita,tpv_id'],
                'vis_fun_id'        => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
                'vis_dt_programada' => ['required', 'date'],
                'vis_obs'           => ['nullable', 'string'],
            ],
            [],
            [
                'vis_tpv_id'        => 'tipo de visita',
                'vis_fun_id'        => 'técnico responsável',
                'vis_dt_programada' => 'data programada',
            ],
        );

        $visita->update($dados);

        return back()->with('success', 'Visita atualizada.');
    }

    // ---- Anexos (evidências/documentos da visita: PDF ou imagem) ----------

    public function anexosStore(Request $request, int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);
        // Visita atestada/cancelada é registro fechado — sem novos anexos.
        $this->assertEditavel($visita);

        $this->guardarAnexos($request, $visita, 'visitas');

        return back()->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    public function anexoDownload(int $id, Anexo $anexo): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);

        return $this->baixarAnexoDe($visita, $anexo);
    }

    public function anexoDestroy(int $id, Anexo $anexo): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);
        $this->assertEditavel($visita);

        $this->excluirAnexoDe($visita, $anexo);

        return back()->with('success', 'Anexo removido.');
    }

    /** Resposta de uma pergunta. */
    public function responder(Request $request, int $id, int $respostaId): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);
        $this->assertEditavel($visita);

        $linha = VisitaResposta::where('vre_vis_id', $visita->vis_id)->findOrFail($respostaId);

        $dados = $request->validate(
            [
                'vre_resposta' => ['nullable', 'string'],
                'vre_pro_id'   => ['nullable', 'integer', 'exists:ali_pergunta_opcao,pro_id'],
                'vre_obs'      => ['nullable', 'string'],
            ],
            [],
            ['vre_resposta' => 'resposta', 'vre_pro_id' => 'opção'],
        );

        $this->assertRespostaCoerente($linha, $dados);

        $linha->update([
            'vre_resposta' => $dados['vre_resposta'] ?? null,
            'vre_pro_id'   => $dados['vre_pro_id'] ?? null,
            'vre_obs'      => $dados['vre_obs'] ?? null,
        ]);

        return back()->with('success', 'Resposta registrada.');
    }

    public function realizar(Request $request, int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);

        $dados = $request->validate(
            ['vis_dt_realizada' => ['required', 'date']],
            [],
            ['vis_dt_realizada' => 'data da realização'],
        );

        app(AplicaQuestionario::class)->registrarRealizacao($visita, $dados['vis_dt_realizada']);

        return back()->with('success', 'Visita marcada como realizada. Agora ela pode ser atestada.');
    }

    public function atestar(Request $request, int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);

        $dados = $request->validate(
            [
                'vis_fun_ateste_id' => ['required', 'integer', 'exists:edu_funcionario,fun_id'],
                'vis_dt_ateste'     => ['required', 'date'],
                'vis_parecer'       => ['nullable', 'string'],
            ],
            [],
            [
                'vis_fun_ateste_id' => 'quem atesta',
                'vis_dt_ateste'     => 'data do ateste',
                'vis_parecer'       => 'parecer',
            ],
        );

        app(AplicaQuestionario::class)->atestar(
            $visita,
            (int) $dados['vis_fun_ateste_id'],
            $dados['vis_dt_ateste'],
            $dados['vis_parecer'] ?? null,
        );

        return back()->with('success', 'Visita atestada.');
    }

    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);

        $dados = $request->validate(
            ['vis_motivo_cancelamento' => ['required', 'string', 'min:5']],
            ['vis_motivo_cancelamento.required' => 'Informe o motivo do cancelamento.'],
            ['vis_motivo_cancelamento' => 'motivo do cancelamento'],
        );

        app(AplicaQuestionario::class)->cancelar($visita, $dados['vis_motivo_cancelamento']);

        return back()->with('success', 'Visita cancelada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $visita = Visita::findOrFail($id);
        $this->assertPodeVer($visita);

        // Visita atestada é registro fechado; realizada tem conteúdo que a
        // consulta e o relatório usam — cancelar preserva o histórico.
        if ($visita->vis_situacao !== Visita::SITUACAO_PROGRAMADA) {
            return back()->with('error', "Visita {$visita->vis_situacao} não pode ser excluída. Cancele-a informando o motivo.");
        }

        $visita->respostas()->delete();

        return $this->safeDelete($visita)
            ?? to_route('alimentacao.visitas.index')->with('success', 'Visita removida.');
    }

    /** Relatório consolidado por unidade escolar e período. */
    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $visitas = $this->baseQuery($request)
            ->with([
                'escola:esc_id,esc_nome', 'tipoVisita:tpv_id,tpv_nome', 'tipoQuestionario:tpq_id,tpq_nome',
                'tecnico:fun_id,fun_nome', 'atestador:fun_id,fun_nome', 'respostas.opcao',
            ])
            ->orderBy('vis_esc_id')
            ->orderBy('vis_dt_programada')
            ->get();

        return $request->input('format') === 'pdf'
            ? $this->exportPdf($visitas, $request)
            : $this->exportCsv($visitas);
    }

    // ------------------------------------------------------------------

    /**
     * A resposta precisa combinar com o tipo congelado: nota fora de 0–10 ou
     * múltipla apontando para opção de outra pergunta viram lixo no relatório.
     */
    private function assertRespostaCoerente(VisitaResposta $linha, array $dados): void
    {
        $resposta = $dados['vre_resposta'] ?? null;

        if ($resposta === null || $resposta === '') {
            return;
        }

        match ($linha->vre_tipo_resposta) {
            Pergunta::TIPO_SIM_NAO => $this->assertSimNao($resposta),
            Pergunta::TIPO_NOTA    => $this->assertNota($resposta),
            Pergunta::TIPO_MULTIPLA => $this->assertOpcaoDaPergunta($linha, $dados['vre_pro_id'] ?? null),
            default => null,
        };
    }

    private function assertSimNao(string $resposta): void
    {
        if (in_array($resposta, ['SIM', 'NAO'], true)) {
            return;
        }

        throw ValidationException::withMessages(['vre_resposta' => 'Responda Sim ou Não.']);
    }

    private function assertNota(string $resposta): void
    {
        if (is_numeric($resposta) && (float) $resposta >= 0 && (float) $resposta <= 10) {
            return;
        }

        throw ValidationException::withMessages(['vre_resposta' => 'A nota deve ser um número de 0 a 10.']);
    }

    private function assertOpcaoDaPergunta(VisitaResposta $linha, ?int $proId): void
    {
        if (! $proId) {
            throw ValidationException::withMessages(['vre_pro_id' => 'Escolha uma das opções.']);
        }

        $pertence = \App\Models\Alimentacao\PerguntaOpcao::where('pro_id', $proId)
            ->where('pro_prg_id', $linha->vre_prg_id)
            ->exists();

        if (! $pertence) {
            throw ValidationException::withMessages(['vre_pro_id' => 'A opção escolhida não é desta pergunta.']);
        }
    }

    private function assertEditavel(Visita $visita): void
    {
        if ($visita->editavel()) {
            return;
        }

        throw ValidationException::withMessages([
            'situacao' => "Esta visita está {$visita->vis_situacao} e não pode mais ser alterada.",
        ]);
    }

    private function assertEscolaPermitida(Request $request, int $escId): void
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        if ($escolasIds === null || in_array($escId, $escolasIds, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'vis_esc_id' => 'Você só pode programar visita para a sua escola.',
        ]);
    }

    private function assertPodeVer(Visita $visita): void
    {
        $escolasIds = UserAccess::escolasIds(request()->user());

        if ($escolasIds === null || in_array($visita->vis_esc_id, $escolasIds, true)) {
            return;
        }

        abort(403, 'Esta visita é de outra escola.');
    }

    /** Só questionário com pergunta ativa: os demais não têm o que aplicar. */
    private function questionariosComPergunta()
    {
        return TipoQuestionario::query()
            ->where('tpq_fl_ativo', true)
            ->whereHas('perguntas', fn ($q) => $q->where('prg_fl_ativo', true))
            ->withCount(['perguntas' => fn ($q) => $q->where('prg_fl_ativo', true)])
            ->orderBy('tpq_ordem')
            ->orderBy('tpq_nome')
            ->get(['tpq_id', 'tpq_nome', 'tpq_ordem'])
            ->map(fn ($q) => [
                'tpq_id'   => $q->tpq_id,
                'tpq_nome' => $q->tpq_nome,
                'perguntas' => $q->perguntas_count,
            ]);
    }

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

        return Visita::query()
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('vis_esc_id', $escolasIds ?: [-1]))
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('esc_id'), fn ($q, $id) => $q->where('vis_esc_id', (int) $id))
            ->when($request->input('tpv_id'), fn ($q, $id) => $q->where('vis_tpv_id', (int) $id))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('vis_situacao', $s))
            ->when($request->input('dt_ini'), fn ($q, $d) => $q->whereDate('vis_dt_programada', '>=', $d))
            ->when($request->input('dt_fim'), fn ($q, $d) => $q->whereDate('vis_dt_programada', '<=', $d));
    }

    private function exportCsv($visitas): StreamedResponse
    {
        $filename = 'visitas_tecnicas_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($visitas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Escola', 'Tipo', 'Programada', 'Realizada', 'Situação', 'Técnico', 'Atestada por', 'Ateste em', 'Pergunta', 'Resposta', 'Observação'], ';');

            foreach ($visitas as $v) {
                $cabecalho = [
                    $v->escola?->esc_nome ?? '',
                    $v->tipoVisita?->tpv_nome ?? '',
                    $v->vis_dt_programada?->format('d/m/Y') ?? '',
                    $v->vis_dt_realizada?->format('d/m/Y') ?? '',
                    $v->vis_situacao,
                    $v->tecnico?->fun_nome ?? '',
                    $v->atestador?->fun_nome ?? '',
                    $v->vis_dt_ateste?->format('d/m/Y') ?? '',
                ];

                if ($v->respostas->isEmpty()) {
                    fputcsv($out, array_merge($cabecalho, ['SEM QUESTIONÁRIO', '', '']), ';');
                    continue;
                }

                foreach ($v->respostas as $r) {
                    fputcsv($out, array_merge($cabecalho, [
                        $r->vre_pergunta_texto,
                        $r->textoResposta(),
                        $r->vre_obs ?? '',
                    ]), ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($visitas, Request $request): HttpResponse
    {
        $collection = collect($visitas);
        $filename   = 'visitas_tecnicas_'.now()->format('Ymd_His').'.pdf';

        $html = view('exports.alimentacao_visitas_pdf', [
            'visitas'   => $collection,
            'periodo'   => $this->periodoTexto($request),
            'total'     => $collection->count(),
            'atestadas' => $collection->where('vis_situacao', Visita::SITUACAO_ATESTADA)->count(),
            'realizadas' => $collection->whereIn('vis_situacao', [Visita::SITUACAO_REALIZADA, Visita::SITUACAO_ATESTADA])->count(),
        ])->render();

        $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
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
}
