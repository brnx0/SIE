<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioPlanoAee;
use App\Models\Turma\Turma;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planos de aula AEE — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\PlanoAeeController com escopo ESTRITO ao
 * autor (dae_user_id = user.id) e sem bypass de admin — coerente com o resto da
 * v1. A rota já é gateada por `app.access:professor_aee` (routes/api.php), então
 * aqui não se checa role.
 *
 * Três fatos que costumam ser "corrigidos" por engano — não são bugs:
 *
 *  1. O plano AEE é por TURMA (dae_tur_id). Não é por aluno, não é por série e
 *     não é por unidade/bimestre: não existe dae_uni_id na tabela. O recorte
 *     temporal é o par livre dae_dt_inicio/dae_dt_fim escolhido pelo professor.
 *
 *  2. Os lookups saem da LOTAÇÃO (edu_funcionario_lotacao + admissão, ativa e
 *     com 'Docente AEE' em lot_funcoes_sala_aula), NÃO da regência em
 *     edu_turma_professor. A frequência e a avaliação AEE usam regência; o plano
 *     usa lotação. São bases diferentes de propósito, herdadas do web — trocar
 *     uma pela outra muda quem consegue planejar.
 *
 *  3. NENHUMA trava de turma aberta ou de período de lançamento se aplica ao
 *     plano AEE. O DiarioAeeGuard existe para frequência/avaliação; o plano é
 *     planejamento e pode ser escrito fora da janela, igual ao web.
 *
 * Regras de estado preservadas do web:
 *  · o plano nasce SEMPRE 'pendente' (status enviado pelo cliente é ignorado);
 *  · editar um plano em 'correcao' o devolve para 'pendente';
 *  · excluir só enquanto 'pendente' — e a exclusão é FÍSICA (safeDelete do web).
 */
class AeePlanosController extends Controller
{
    /** Lista os planos AEE do professor, mais recentes primeiro. */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'status' => ['nullable', 'string', 'in:pendente,aprovado,reprovado,correcao'],
            'tur_id' => ['nullable', 'integer'],
        ]);

        $planos = DiarioPlanoAee::query()
            ->where('dae_user_id', (int) $request->user()->id)
            ->when($filtros['status'] ?? null, fn ($q, $s) => $q->where('dae_status', $s))
            ->when($filtros['tur_id'] ?? null, fn ($q, $t) => $q->where('dae_tur_id', (int) $t))
            // Eager load resolve turma+escola em 2 consultas, não 2 por plano.
            ->with(['turma:tur_id,tur_nome', 'escola:esc_id,esc_nome'])
            ->orderByDesc('dae_dt_inicio')
            ->orderByDesc('dae_id')
            ->get();

        return response()->json([
            'planos' => $planos->map(fn ($p) => $this->resumo($p))->values(),
        ]);
    }

    /**
     * Opções para montar o formulário: anos, escolas e turmas AEE do professor.
     *
     * Adaptação de FORMATO (não de regra): o web tem dois endpoints em cascata
     * (lookupEscolas e lookupTurmas, este último exigindo esc_id e devolvendo []
     * sem ele). Aqui vai tudo num round-trip só, porque o app monta os seletores
     * offline. A BASE continua sendo a lotação 'Docente AEE', idêntica ao web.
     */
    public function lookups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['nullable', 'integer', 'exists:cfg_ano_letivo,anl_id'],
        ]);

        $funId = (int) $request->user()->fun_id;
        abort_unless($funId, 422, 'Seu usuário não possui vínculo de funcionário.');

        $anlId = (int) ($data['anl_id'] ?? 0);

        // Lotações ativas como 'Docente AEE' — fonte única dos três lookups.
        $lotacoes = $this->lotacaoAee($funId)
            ->get(['l.lot_esc_id', 'l.lot_dt_inicio', 'l.lot_dt_fim']);

        if ($lotacoes->isEmpty()) {
            return response()->json(['anos' => [], 'escolas' => [], 'turmas' => []]);
        }

        // Anos letivos que se SOBREPÕEM a alguma lotação AEE — mesma lógica de
        // anosLetivosDoProfessor no web. Não é "ano das turmas": um professor
        // recém-lotado ainda não tem turma e mesmo assim precisa ver o ano.
        $anos = DB::table('cfg_ano_letivo')
            ->whereNull('anl_deleted_at')
            ->where(function ($outer) use ($lotacoes) {
                foreach ($lotacoes as $lot) {
                    $outer->orWhere(function ($q) use ($lot) {
                        $q->where(function ($qq) use ($lot) {
                            $qq->whereNull('anl_dt_fim')
                                ->orWhere('anl_dt_fim', '>=', $lot->lot_dt_inicio);
                        });
                        // Lotação sem fim é vigente: não limita o ano pela direita.
                        if ($lot->lot_dt_fim) {
                            $q->where('anl_dt_inicio_ano', '<=', $lot->lot_dt_fim);
                        }
                    });
                }
            })
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
            ->map(fn ($a) => [
                'anl_id'              => (int) $a->anl_id,
                'anl_ano'             => (int) $a->anl_ano,
                'anl_fl_em_exercicio' => (bool) $a->anl_fl_em_exercicio,
            ]);

        $escolas = $this->lotacaoAee($funId)
            ->join('edu_escola as e', 'e.esc_id', '=', 'l.lot_esc_id')
            ->distinct()
            ->orderBy('e.esc_nome')
            ->get(['e.esc_id', 'e.esc_nome'])
            ->map(fn ($e) => ['esc_id' => (int) $e->esc_id, 'esc_nome' => $e->esc_nome]);

        // Só modalidade AEE, e só nas escolas onde há lotação — exatamente o que
        // o web checa por escola antes de listar (o `temLotacao` do lookupTurmas).
        // Sem filtro de tur_situacao: o web também lista turma fechada aqui.
        $escIds = $lotacoes->pluck('lot_esc_id')->unique()->values()->all();

        $turmas = DB::table('edu_turma as t')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->whereIn('t.tur_esc_id', $escIds)
            ->where('t.tur_modalidade', Turma::MODALIDADE_AEE)
            ->whereNull('t.tur_deleted_at')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 'e.esc_nome'])
            ->map(fn ($t) => [
                'tur_id'   => (int) $t->tur_id,
                'tur_nome' => $t->tur_nome,
                'esc_id'   => (int) $t->tur_esc_id,
                'esc_nome' => $t->esc_nome,
                'anl_id'   => (int) $t->tur_anl_id,
            ]);

        return response()->json([
            'anos'    => $anos->values(),
            'escolas' => $escolas->values(),
            'turmas'  => $turmas->values(),
        ]);
    }

    /** Detalhe do plano, com o retorno da coordenação. */
    public function show(Request $request, DiarioPlanoAee $plano): JsonResponse
    {
        $this->assertDono($request, $plano);
        $plano->load(['turma:tur_id,tur_nome', 'escola:esc_id,esc_nome', 'anoLetivo:anl_id,anl_ano']);

        $validador = $plano->dae_validado_por_user_id
            ? DB::table('users')->where('id', $plano->dae_validado_por_user_id)->value('name')
            : null;

        return response()->json(array_merge($this->resumo($plano), [
            'dae_esc_id'      => (int) $plano->dae_esc_id,
            'dae_anl_id'      => (int) $plano->dae_anl_id,
            'anl_ano'         => $plano->anoLetivo ? (int) $plano->anoLetivo->anl_ano : null,
            'dae_objetivo'    => $plano->dae_objetivo,
            'dae_diagnostico' => $plano->dae_diagnostico,
            'dae_area_desenv' => $plano->dae_area_desenv,
            'dae_metas'       => $plano->dae_metas,
            'dae_estrategias' => $plano->dae_estrategias,
            'dae_recursos'    => $plano->dae_recursos,
            'dae_avaliacao'   => $plano->dae_avaliacao,
            // Retorno da coordenação — somente leitura para o professor.
            'validacao'       => [
                'observacao'   => $plano->dae_obs_coordenador,
                'validado_por' => $validador,
                'validado_em'  => $plano->dae_validado_em
                    ? Carbon::parse($plano->dae_validado_em)->toDateTimeString()
                    : null,
            ],
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validarPlano($request);
        $this->validarRegras($request, $data, null);

        $plano = DiarioPlanoAee::create(array_merge($data, [
            'dae_user_id' => (int) $request->user()->id,
            // Nasce sempre pendente — o cliente não escolhe o status.
            'dae_status'  => DiarioPlanoAee::STATUS_PENDENTE,
        ]));

        return response()->json(['ok' => true, 'dae_id' => (int) $plano->dae_id], 201);
    }

    public function update(Request $request, DiarioPlanoAee $plano): JsonResponse
    {
        $this->assertDono($request, $plano);

        // Plano APROVADO é definitivo — a coordenação já validou. Não recebe mais
        // edição, em NENHUM lado: web (StorePlanoAulaRequest / o AEE equivalente),
        // esta API e a UI de ambos. Regra firme, não só cosmética.
        abort_if($plano->dae_status === DiarioPlanoAee::STATUS_APROVADO, 422, 'Plano aprovado não pode ser editado.');

        $data = $this->validarPlano($request);
        $this->validarRegras($request, $data, (int) $plano->dae_id);

        if ($plano->dae_status === DiarioPlanoAee::STATUS_CORRECAO) {
            // Reenviar um plano "em correção" o devolve para a fila da coordenação.
            $data['dae_status'] = DiarioPlanoAee::STATUS_PENDENTE;
        }

        $plano->update($data);

        return response()->json(['ok' => true, 'dae_status' => $plano->fresh()->dae_status]);
    }

    /** Exclusão FÍSICA — e só enquanto pendente (igual ao web). */
    public function destroy(Request $request, DiarioPlanoAee $plano): JsonResponse
    {
        $this->assertDono($request, $plano);

        abort_unless(
            $plano->isPendente(),
            422,
            'Plano só pode ser excluído enquanto estiver pendente.',
        );

        try {
            // forceDelete, não delete: o web usa safeDelete(), que é físico
            // apesar de o model ter SoftDeletes.
            $plano->forceDelete();
        } catch (QueryException $e) {
            // 23503 = FK violation. Hoje só edu_diario_aee_conteudo aponta para
            // cá, e com ON DELETE SET NULL — mas o web trata, então tratamos.
            if ($e->getCode() === '23503') {
                abort(422, 'Este registro está vinculado a outros dados e não pode ser excluído. Inative-o caso não queira mais utilizá-lo.');
            }
            throw $e;
        }

        return response()->json(['ok' => true]);
    }

    // ============ Helpers ============

    /**
     * Base dos lookups: lotação ATIVA como 'Docente AEE'.
     * Nova instância a cada chamada — o builder é mutável e é reusado em joins
     * diferentes (escolas x lotações cruas).
     */
    private function lotacaoAee(int $funId)
    {
        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_fl_ativo', true)
            ->whereJsonContains('l.lot_funcoes_sala_aula', 'Docente AEE');
    }

    private function resumo(DiarioPlanoAee $p): array
    {
        return [
            'dae_id'        => (int) $p->dae_id,
            'dae_tema'      => $p->dae_tema,
            'dae_status'    => $p->dae_status,
            'dae_dt_inicio' => $p->dae_dt_inicio ? Carbon::parse($p->dae_dt_inicio)->toDateString() : null,
            'dae_dt_fim'    => $p->dae_dt_fim ? Carbon::parse($p->dae_dt_fim)->toDateString() : null,
            'tur_id'        => (int) $p->dae_tur_id,
            'tur_nome'      => $p->turma?->tur_nome,
            'esc_nome'      => $p->escola?->esc_nome,
            // A coluna chama dae_obs_coordenador (não dae_obs_validacao): é o
            // texto que a coordenação escreve ao aprovar/reprovar/devolver.
            'tem_observacao' => filled($p->dae_obs_coordenador),
        ];
    }

    /**
     * O web dá um flash 'Você só pode editar planos de aula AEE que você mesmo
     * criou.' e redireciona para a lista. Numa API o equivalente é 403 com a
     * mesma mensagem — inclusive na leitura, porque a v1 não tem o modo
     * "só-visualização" que no web deixa um perfil de leitura abrir plano alheio.
     */
    private function assertDono(Request $request, DiarioPlanoAee $plano): void
    {
        abort_unless(
            (int) $plano->dae_user_id === (int) $request->user()->id,
            403,
            'Você só pode editar planos de aula AEE que você mesmo criou.',
        );
    }

    /** Campos e limites idênticos ao StorePlanoAeeRequest do web. */
    private function validarPlano(Request $request): array
    {
        return $request->validate([
            'dae_esc_id'      => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dae_anl_id'      => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dae_tur_id'      => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dae_tema'        => ['required', 'string', 'max:255'],
            'dae_dt_inicio'   => ['required', 'date'],
            'dae_dt_fim'      => ['required', 'date', 'after_or_equal:dae_dt_inicio'],
            'dae_objetivo'    => ['required', 'string', 'max:10000'],
            'dae_diagnostico' => ['nullable', 'string', 'max:5000'],
            'dae_area_desenv' => ['nullable', 'string', 'max:5000'],
            'dae_metas'       => ['required', 'string', 'max:10000'],
            'dae_estrategias' => ['required', 'string', 'max:10000'],
            'dae_recursos'    => ['required', 'string', 'max:5000'],
            'dae_avaliacao'   => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * Regras que o web aplica no withValidator do StorePlanoAeeRequest, na mesma
     * ordem. Sem o `if (! $user->isAdmin())` que o web usa para pular a lotação:
     * a v1 não tem bypass de admin.
     */
    private function validarRegras(Request $request, array $data, ?int $exceto): void
    {
        $turId = (int) $data['dae_tur_id'];

        // 1) A turma precisa ser de AEE.
        $turma = DB::table('edu_turma')
            ->where('tur_id', $turId)
            ->whereNull('tur_deleted_at')
            ->first(['tur_esc_id', 'tur_modalidade']);

        if (! $turma || $turma->tur_modalidade !== Turma::MODALIDADE_AEE) {
            throw ValidationException::withMessages(['dae_tur_id' => 'Apenas turmas AEE são permitidas.']);
        }

        $funId = (int) $request->user()->fun_id;
        if (! $funId) {
            throw ValidationException::withMessages(['dae_tur_id' => 'Seu usuário não possui vínculo de funcionário.']);
        }

        // 2) Lotação 'Docente AEE' na escola DA TURMA — repare que o web checa
        // tur_esc_id, não o dae_esc_id que veio no corpo. Os dois podem divergir
        // e o web não reclama disso; mantemos a mesma brecha (paridade).
        $temLotacao = $this->lotacaoAee($funId)
            ->where('l.lot_esc_id', $turma->tur_esc_id)
            ->exists();

        if (! $temLotacao) {
            throw ValidationException::withMessages([
                'dae_tur_id' => 'Você precisa ter lotação ativa como "Docente AEE" na escola desta turma.',
            ]);
        }

        // 3) Sem outro plano SEU para a mesma turma com datas idênticas. Espelha
        // o índice único parcial (dae_user_id, dae_tur_id, dt_inicio, dt_fim) —
        // sem esta checagem o cliente levaria um 500 vindo do Postgres.
        $duplicado = DiarioPlanoAee::query()
            ->where('dae_user_id', (int) $request->user()->id)
            ->where('dae_tur_id', $turId)
            ->whereDate('dae_dt_inicio', $data['dae_dt_inicio'])
            ->whereDate('dae_dt_fim', $data['dae_dt_fim'])
            ->when($exceto, fn ($q) => $q->where('dae_id', '!=', $exceto))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages([
                'dae_dt_inicio' => 'Já existe plano para esta turma no mesmo período.',
            ]);
        }
    }
}
