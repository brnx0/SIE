<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Pergunta;
use App\Models\Alimentacao\PerguntaOpcao;
use App\Models\Alimentacao\TipoQuestionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perguntas do questionário de visita técnica, com as opções da múltipla escolha.
 *
 * Pergunta já usada em visita não é excluída: inativa-se. As respostas antigas
 * guardam o texto congelado, então a visita continua legível de qualquer jeito —
 * mas apagar a linha quebraria a FK e o rastro.
 */
class PerguntaController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 25;

        $perguntas = $this->baseQuery($request)
            ->with(['tipoQuestionario:tpq_id,tpq_nome', 'opcoes'])
            ->withCount('respostas')
            ->orderBy('prg_tpq_id')
            ->orderBy('prg_ordem')
            ->orderBy('prg_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($p) => [
                'prg_id'             => $p->prg_id,
                'prg_tpq_id'         => $p->prg_tpq_id,
                'prg_ordem'          => $p->prg_ordem,
                'prg_texto'          => $p->prg_texto,
                'prg_tipo_resposta'  => $p->prg_tipo_resposta,
                'tipo_label'         => Pergunta::TIPOS_LABEL[$p->prg_tipo_resposta] ?? $p->prg_tipo_resposta,
                'prg_fl_obrigatoria' => $p->prg_fl_obrigatoria,
                'prg_fl_ativo'       => $p->prg_fl_ativo,
                'questionario_nome'  => $p->tipoQuestionario?->tpq_nome,
                'opcoes'             => $p->opcoes->sortBy('pro_ordem')->map(fn ($o) => [
                    'pro_id'    => $o->pro_id,
                    'pro_texto' => $o->pro_texto,
                    'pro_peso'  => $o->pro_peso !== null ? (float) $o->pro_peso : null,
                ])->values(),
                // Pergunta já respondida não pode ser excluída — só inativada.
                'usada'              => $p->respostas_count > 0,
            ]);

        return Inertia::render('alimentacao/perguntas/Index', [
            'perguntas'     => $perguntas,
            'questionarios' => TipoQuestionario::orderBy('tpq_nome')->get(['tpq_id', 'tpq_nome', 'tpq_fl_ativo']),
            'tipos'         => Pergunta::TIPOS_LABEL,
            'filters'       => [
                'search'   => $request->input('search', ''),
                'tpq_id'   => $request->input('tpq_id', ''),
                'situacao' => $request->input('situacao', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        $pergunta = Pergunta::create($dados);
        $this->sincronizarOpcoes($pergunta, $request);

        return back()->with('success', 'Pergunta cadastrada.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $pergunta = Pergunta::findOrFail($id);
        $dados    = $this->validar($request, $id);

        $this->assertTipoAlteravel($pergunta, $dados['prg_tipo_resposta']);

        $pergunta->update($dados);
        $this->sincronizarOpcoes($pergunta, $request);

        return back()->with('success', 'Pergunta atualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $pergunta = Pergunta::withCount('respostas')->findOrFail($id);

        if ($pergunta->respostas_count > 0) {
            return back()->with('error', 'Esta pergunta já foi respondida em visitas e não pode ser excluída. Inative-a para não aparecer nas próximas.');
        }

        $pergunta->opcoes()->delete();

        return $this->safeDelete($pergunta)
            ?? back()->with('success', 'Pergunta removida.');
    }

    // ------------------------------------------------------------------

    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $request->merge([
            'prg_fl_obrigatoria' => $request->boolean('prg_fl_obrigatoria'),
            'prg_fl_ativo'       => $request->boolean('prg_fl_ativo'),
        ]);

        $dados = $request->validate(
            [
                'prg_tpq_id'         => ['required', 'integer', 'exists:ali_tipo_questionario,tpq_id'],
                'prg_ordem'          => ['required', 'integer', 'min:1'],
                'prg_texto'          => ['required', 'string'],
                'prg_tipo_resposta'  => ['required', 'string', Rule::in(Pergunta::TIPOS)],
                'prg_fl_obrigatoria' => ['boolean'],
                'prg_fl_ativo'       => ['boolean'],
                'opcoes'             => ['array'],
                'opcoes.*.pro_texto' => ['required', 'string', 'max:150'],
                'opcoes.*.pro_peso'  => ['nullable', 'numeric'],
            ],
            [],
            [
                'prg_tpq_id'        => 'questionário',
                'prg_ordem'         => 'ordem',
                'prg_texto'         => 'pergunta',
                'prg_tipo_resposta' => 'tipo de resposta',
            ],
        );

        // Múltipla escolha sem opção não tem o que escolher.
        if ($dados['prg_tipo_resposta'] === Pergunta::TIPO_MULTIPLA && empty($dados['opcoes'])) {
            throw ValidationException::withMessages([
                'opcoes' => 'Pergunta de múltipla escolha precisa de ao menos uma opção de resposta.',
            ]);
        }

        return collect($dados)->except('opcoes')->all();
    }

    /**
     * Trocar o tipo de uma pergunta já respondida faria as respostas antigas
     * deixarem de fazer sentido (um "Sim" virando nota, por exemplo). O texto
     * congelado protege a leitura, mas o tipo muda a interpretação do valor.
     */
    private function assertTipoAlteravel(Pergunta $pergunta, string $novoTipo): void
    {
        if ($pergunta->prg_tipo_resposta === $novoTipo || ! $pergunta->respostas()->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'prg_tipo_resposta' => 'Esta pergunta já foi respondida em visitas — o tipo de resposta não pode mudar.'
                .' Inative-a e cadastre outra pergunta com o novo tipo.',
        ]);
    }

    private function sincronizarOpcoes(Pergunta $pergunta, Request $request): void
    {
        if (! $pergunta->ehMultipla()) {
            // Trocou de múltipla para outro tipo: as opções não servem mais.
            $pergunta->opcoes()->whereDoesntHave('pergunta.respostas')->delete();

            return;
        }

        $opcoes = collect($request->input('opcoes', []));
        $manter = [];

        foreach ($opcoes->values() as $i => $opcao) {
            $registro = PerguntaOpcao::updateOrCreate(
                [
                    'pro_prg_id' => $pergunta->prg_id,
                    'pro_id'     => $opcao['pro_id'] ?? null,
                ],
                [
                    'pro_ordem' => $i + 1,
                    'pro_texto' => $opcao['pro_texto'],
                    'pro_peso'  => $opcao['pro_peso'] ?? null,
                ],
            );

            $manter[] = $registro->pro_id;
        }

        // Opção escolhida em alguma resposta não pode sumir: a resposta antiga
        // aponta para ela.
        $pergunta->opcoes()
            ->whereNotIn('pro_id', $manter ?: [-1])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                  ->from('ali_visita_resposta')
                  ->whereColumn('ali_visita_resposta.vre_pro_id', 'ali_pergunta_opcao.pro_id');
            })
            ->delete();
    }

    private function baseQuery(Request $request)
    {
        $situacao = $request->input('situacao');

        return Pergunta::query()
            ->when($request->input('search'), fn ($q, $s) => $q->search($s))
            ->when($request->input('tpq_id'), fn ($q, $id) => $q->where('prg_tpq_id', (int) $id))
            ->when($situacao !== '' && $situacao !== null,
                fn ($q) => $q->where('prg_fl_ativo', $situacao === 'ativo'));
    }
}
