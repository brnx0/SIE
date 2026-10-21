<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Diario\DiarioPlanoIndicador;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planos de aula — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\PlanoAulaController, com escopo ESTRITO:
 * o professor só enxerga e mexe nos PRÓPRIOS planos (dpa_user_id = user.id),
 * sem bypass de admin — coerente com o resto da v1.
 *
 * Regras preservadas do web:
 *  · o plano nasce SEMPRE 'pendente' (status enviado pelo cliente é ignorado);
 *  · editar um plano em 'correcao' o devolve para 'pendente';
 *  · excluir só enquanto 'pendente' e só o próprio — e a exclusão é FÍSICA;
 *  · as datas precisam caber DENTRO de [uni_dt_inicio, uni_dt_fim] — sem a
 *    extensão, diferente das travas de lançamento;
 *  · não pode haver outro plano seu com a mesma série+disciplina e as MESMAS
 *    datas de início e fim.
 *
 * O tema tem limite REAL de 50 caracteres (a coluna é varchar(255), mas a
 * validação do web é max:50 — manter igual para não divergir).
 */
class PlanosController extends Controller
{
    /** Lista os planos do professor, mais recentes primeiro. */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'status' => ['nullable', 'string', 'in:pendente,aprovado,reprovado,correcao'],
            'uni_id' => ['nullable', 'integer'],
            'dis_id' => ['nullable', 'integer'],
        ]);

        $planos = DiarioPlanoAula::query()
            ->where('dpa_user_id', (int) $request->user()->id)
            ->when($filtros['status'] ?? null, fn ($q, $s) => $q->where('dpa_status', $s))
            ->when($filtros['uni_id'] ?? null, fn ($q, $u) => $q->where('dpa_uni_id', $u))
            ->when($filtros['dis_id'] ?? null, fn ($q, $d) => $q->where('dpa_dis_id', $d))
            ->with([
                'serie:ser_id,ser_nome',
                'disciplina:dis_id,dis_nome',
                'unidade:uni_id,uni_numero,uni_tipo',
            ])
            ->orderByDesc('dpa_dt_inicio')
            ->orderByDesc('dpa_id')
            ->get();

        return response()->json([
            'planos' => $planos->map(fn ($p) => $this->resumo($p))->values(),
        ]);
    }

    /** Detalhe do plano, com indicadores e o retorno da coordenação. */
    public function show(Request $request, DiarioPlanoAula $plano): JsonResponse
    {
        $this->assertDono($request, $plano);
        $plano->load(['serie:ser_id,ser_nome', 'disciplina:dis_id,dis_nome', 'unidade:uni_id,uni_numero,uni_tipo']);

        $indicadores = DB::table('edu_diario_plano_indicador as pi')
            ->join('edu_serie_indicador as i', 'i.ind_id', '=', 'pi.dpi_ind_id')
            ->where('pi.dpi_dpa_id', $plano->dpa_id)
            ->orderBy('i.ind_id')
            ->get(['i.ind_id', 'i.ind_descricao', 'i.ind_fl_ativo'])
            ->map(fn ($i) => [
                'ind_id'        => (int) $i->ind_id,
                'ind_descricao' => $i->ind_descricao,
                'ind_fl_ativo'  => (bool) $i->ind_fl_ativo,
            ]);

        $validador = $plano->dpa_validado_por_user_id
            ? DB::table('users')->where('id', $plano->dpa_validado_por_user_id)->value('name')
            : null;

        return response()->json(array_merge($this->resumo($plano), [
            'dpa_objeto_conhecimento'      => $plano->dpa_objeto_conhecimento,
            'dpa_estrategias'              => $plano->dpa_estrategias,
            'dpa_recursos'                 => $plano->dpa_recursos,
            'dpa_competencias'             => $plano->dpa_competencias,
            'dpa_avaliacao'                => $plano->dpa_avaliacao,
            'dpa_objetivos_complementares' => $plano->dpa_objetivos_complementares,
            'dpa_esc_id'                   => (int) $plano->dpa_esc_id,
            'dpa_anl_id'                   => (int) $plano->dpa_anl_id,
            'indicadores'                  => $indicadores,
            // Retorno da coordenação — somente leitura para o professor.
            'validacao'                    => [
                'observacao'  => $plano->dpa_obs_coordenador,
                'validado_por' => $validador,
                'validado_em' => $plano->dpa_validado_em ? Carbon::parse($plano->dpa_validado_em)->toDateTimeString() : null,
            ],
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validarPlano($request);
        $this->validarRegras($request, $data, null);

        $plano = DB::transaction(function () use ($request, $data) {
            $indicadores = collect($data['indicadores'] ?? [])->map(fn ($i) => (int) $i)->unique();
            unset($data['indicadores']);

            $plano = DiarioPlanoAula::create(array_merge($data, [
                'dpa_user_id' => (int) $request->user()->id,
                // Nasce sempre pendente — o cliente não escolhe o status.
                'dpa_status'  => DiarioPlanoAula::STATUS_PENDENTE,
            ]));

            $this->regravarIndicadores((int) $plano->dpa_id, $indicadores);

            return $plano;
        });

        return response()->json(['ok' => true, 'dpa_id' => (int) $plano->dpa_id], 201);
    }

    public function update(Request $request, DiarioPlanoAula $plano): JsonResponse
    {
        $this->assertDono($request, $plano);
        // Plano APROVADO é definitivo — a coordenação já validou. Não recebe edição.
        abort_if($plano->dpa_status === DiarioPlanoAula::STATUS_APROVADO, 422, 'Plano aprovado não pode ser editado.');
        $data = $this->validarPlano($request);
        $this->validarRegras($request, $data, (int) $plano->dpa_id);

        DB::transaction(function () use ($plano, $data) {
            $indicadores = collect($data['indicadores'] ?? [])->map(fn ($i) => (int) $i)->unique();
            unset($data['indicadores']);

            // Reenviar um plano "em correção" o devolve para a fila da coordenação.
            if ($plano->dpa_status === DiarioPlanoAula::STATUS_CORRECAO) {
                $data['dpa_status'] = DiarioPlanoAula::STATUS_PENDENTE;
            }

            $plano->update($data);
            $this->regravarIndicadores((int) $plano->dpa_id, $indicadores);
        });

        return response()->json(['ok' => true, 'dpa_status' => $plano->fresh()->dpa_status]);
    }

    /** Exclusão FÍSICA — e só enquanto pendente (igual ao web). */
    public function destroy(Request $request, DiarioPlanoAula $plano): JsonResponse
    {
        $this->assertDono($request, $plano);

        abort_unless(
            $plano->dpa_status === DiarioPlanoAula::STATUS_PENDENTE,
            422,
            'Plano só pode ser excluído enquanto estiver pendente.',
        );

        DB::transaction(function () use ($plano) {
            DiarioPlanoIndicador::where('dpi_dpa_id', $plano->dpa_id)->delete();
            $plano->forceDelete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Opções de contexto para criar/editar um plano.
     *
     * O plano NÃO é de uma turma: ele vale para a SÉRIE + disciplina na escola
     * e ano (não há tur_id em edu_diario_plano_aula). Por isso as opções vêm da
     * regência do professor (edu_turma_professor), não de uma turma específica.
     */
    public function lookups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['nullable', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer'],
            'ser_id' => ['nullable', 'integer'],
        ]);

        $funId = (int) $request->user()->fun_id;
        abort_unless($funId, 422, 'Seu usuário não possui vínculo de funcionário.');

        /** Base: turmas REGULARES em que o professor tem regência. */
        $base = fn () => DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('t.tur_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at');

        $anos = (clone $base())
            ->join('cfg_ano_letivo as a', 'a.anl_id', '=', 't.tur_anl_id')
            ->distinct()
            ->orderByDesc('a.anl_ano')
            ->get(['a.anl_id', 'a.anl_ano', 'a.anl_fl_em_exercicio'])
            ->map(fn ($r) => [
                'anl_id'              => (int) $r->anl_id,
                'anl_ano'             => (int) $r->anl_ano,
                'anl_fl_em_exercicio' => (bool) $r->anl_fl_em_exercicio,
            ]);

        $anlId = (int) ($data['anl_id'] ?? 0);

        $escolas = $anlId
            ? (clone $base())
                ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
                ->where('t.tur_anl_id', $anlId)
                ->distinct()
                ->orderBy('e.esc_nome')
                ->get(['e.esc_id', 'e.esc_nome'])
                ->map(fn ($r) => ['esc_id' => (int) $r->esc_id, 'esc_nome' => $r->esc_nome])
            : collect();

        $escId = (int) ($data['esc_id'] ?? 0);

        $series = $anlId && $escId
            ? (clone $base())
                ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->distinct()
                ->orderBy('s.ser_nome')
                // ser_fl_multi: o front usa p/ liberar a escolha (opcional) da turma.
                ->get(['s.ser_id', 's.ser_nome', 's.ser_fl_multi'])
                ->map(fn ($r) => [
                    'ser_id'       => (int) $r->ser_id,
                    'ser_nome'     => $r->ser_nome,
                    'ser_fl_multi' => (bool) $r->ser_fl_multi,
                ])
            : collect();

        $serId = (int) ($data['ser_id'] ?? 0);

        // Turmas da série em que o professor tem regência — escolha OPCIONAL
        // da turma do plano (em branco = todas as turmas da série).
        $turmas = $anlId && $escId && $serId
            ? (clone $base())
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->where('t.tur_ser_id', $serId)
                ->distinct()
                ->orderBy('t.tur_nome')
                ->get(['t.tur_id', 't.tur_nome'])
                ->map(fn ($r) => ['tur_id' => (int) $r->tur_id, 'tur_nome' => $r->tur_nome])
            : collect();

        // Disciplinas que ESTE professor leciona nesta série/escola/ano.
        $disciplinas = $anlId && $escId && $serId
            ? (clone $base())
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->where('t.tur_ser_id', $serId)
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get(['d.dis_id', 'd.dis_nome'])
                ->map(fn ($r) => ['dis_id' => (int) $r->dis_id, 'dis_nome' => $r->dis_nome])
            : collect();

        $unidades = $anlId
            ? DB::table('cfg_unidade')
                ->where('uni_anl_id', $anlId)
                ->orderBy('uni_numero')
                ->get(['uni_id', 'uni_tipo', 'uni_numero', 'uni_dt_inicio', 'uni_dt_fim'])
                ->map(fn ($u) => [
                    'uni_id'        => (int) $u->uni_id,
                    'uni_tipo'      => $u->uni_tipo,
                    'uni_numero'    => (int) $u->uni_numero,
                    'uni_dt_inicio' => Carbon::parse($u->uni_dt_inicio)->toDateString(),
                    // Sem extensão: é o limite que vale para as datas do plano.
                    'uni_dt_fim'    => Carbon::parse($u->uni_dt_fim)->toDateString(),
                ])
            : collect();

        return response()->json([
            'anos'        => $anos->values(),
            'escolas'     => $escolas->values(),
            'series'      => $series->values(),
            'turmas'      => $turmas->values(),
            'disciplinas' => $disciplinas->values(),
            'unidades'    => $unidades->values(),
        ]);
    }

    /**
     * Indicadores da série+disciplina para montar a seleção.
     * `incluir_ids` preserva indicadores já vinculados que foram inativados —
     * sem isso, editar um plano antigo os perderia silenciosamente.
     */
    public function indicadores(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ser_id'      => ['required', 'integer'],
            'dis_id'      => ['required', 'integer'],
            'incluir_ids' => ['nullable'],
        ]);

        $incluir = collect(is_array($data['incluir_ids'] ?? null)
            ? $data['incluir_ids']
            : explode(',', (string) ($data['incluir_ids'] ?? '')))
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->all();

        $lista = DB::table('edu_serie_indicador')
            ->where('ind_ser_id', (int) $data['ser_id'])
            ->where('ind_dis_id', (int) $data['dis_id'])
            ->whereNull('ind_deleted_at')
            ->where(function ($q) use ($incluir) {
                $q->where('ind_fl_ativo', true);
                if ($incluir) {
                    $q->orWhereIn('ind_id', $incluir);
                }
            })
            ->orderBy('ind_id')
            ->limit(500)
            ->get(['ind_id', 'ind_descricao', 'ind_fl_ativo'])
            ->map(fn ($i) => [
                'ind_id'        => (int) $i->ind_id,
                'ind_descricao' => $i->ind_descricao,
                'ind_fl_ativo'  => (bool) $i->ind_fl_ativo,
            ]);

        return response()->json(['indicadores' => $lista]);
    }

    // ============ Helpers ============

    private function resumo(DiarioPlanoAula $p): array
    {
        return [
            'dpa_id'         => (int) $p->dpa_id,
            'dpa_tema'       => $p->dpa_tema,
            'dpa_status'     => $p->dpa_status,
            'dpa_dt_inicio'  => $p->dpa_dt_inicio ? Carbon::parse($p->dpa_dt_inicio)->toDateString() : null,
            'dpa_dt_fim'     => $p->dpa_dt_fim ? Carbon::parse($p->dpa_dt_fim)->toDateString() : null,
            'dpa_ser_id'     => (int) $p->dpa_ser_id,
            'ser_nome'       => $p->serie?->ser_nome,
            'dpa_tur_id'     => $p->dpa_tur_id ? (int) $p->dpa_tur_id : null,
            'tur_nome'       => $p->turma?->tur_nome,
            'dpa_dis_id'     => (int) $p->dpa_dis_id,
            'dis_nome'       => $p->disciplina?->dis_nome,
            'dpa_uni_id'     => (int) $p->dpa_uni_id,
            'uni_label'      => $p->unidade ? $p->unidade->uni_numero . 'º ' . $p->unidade->uni_tipo : null,
            'tem_observacao' => filled($p->dpa_obs_coordenador),
        ];
    }

    private function assertDono(Request $request, DiarioPlanoAula $plano): void
    {
        abort_unless(
            (int) $plano->dpa_user_id === (int) $request->user()->id,
            403,
            'Você só pode acessar planos de aula que você mesmo criou.',
        );
    }

    private function validarPlano(Request $request): array
    {
        return $request->validate([
            'dpa_esc_id'                   => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'dpa_anl_id'                   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'dpa_ser_id'                   => ['required', 'integer', 'exists:edu_serie,ser_id'],
            // Opcional: em branco o plano vale para todas as turmas da série.
            'dpa_tur_id'                   => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'dpa_dis_id'                   => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dpa_uni_id'                   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            // 50 é o limite real cobrado pelo web, apesar da coluna ser varchar(255).
            'dpa_tema'                     => ['required', 'string', 'max:50'],
            'dpa_dt_inicio'                => ['required', 'date'],
            // Sem isto o intervalo pode nascer invertido: as regras seguintes só
            // checam cada data contra a unidade, nunca a ordem entre elas — e um
            // plano invertido nunca casa com `dt_inicio <= dia <= dt_fim`, então
            // some do "planejamento executado" sem erro nenhum.
            'dpa_dt_fim'                   => ['required', 'date', 'after_or_equal:dpa_dt_inicio'],
            'dpa_objeto_conhecimento'      => ['required', 'string', 'max:5000'],
            'dpa_estrategias'              => ['nullable', 'string', 'max:10000'],
            'dpa_recursos'                 => ['required', 'string', 'max:5000'],
            'dpa_competencias'             => ['nullable', 'string', 'max:5000'],
            'dpa_avaliacao'                => ['nullable', 'string', 'max:5000'],
            'dpa_objetivos_complementares' => ['nullable', 'string', 'max:5000'],
            'indicadores'                  => ['nullable', 'array'],
            'indicadores.*'                => ['integer', 'exists:edu_serie_indicador,ind_id'],
        ]);
    }

    /** Regras que o web aplica no withValidator, na mesma ordem. */
    private function validarRegras(Request $request, array $data, ?int $exceto): void
    {
        // 1) Datas dentro do período da unidade — SEM a extensão.
        $uni = DB::table('cfg_unidade')->where('uni_id', $data['dpa_uni_id'])->first(['uni_dt_inicio', 'uni_dt_fim']);

        // 1a) Período já encerrado não recebe plano NOVO: planejar é para frente,
        // não se planeja um período que já passou. Na EDIÇÃO ($exceto != null) o
        // período do próprio plano continua valendo — senão um plano antigo não
        // poderia mais ser corrigido.
        if ($exceto === null && $uni && $uni->uni_dt_fim
            && Carbon::parse($uni->uni_dt_fim)->toDateString() < now()->toDateString()
        ) {
            throw ValidationException::withMessages([
                'dpa_uni_id' => 'Este período já encerrou. Escolha um período em andamento ou futuro para o plano.',
            ]);
        }

        if ($uni && $uni->uni_dt_inicio && $uni->uni_dt_fim) {
            $ini = Carbon::parse($uni->uni_dt_inicio)->toDateString();
            $fim = Carbon::parse($uni->uni_dt_fim)->toDateString();
            $msg = 'A data precisa estar dentro do período selecionado ('
                . Carbon::parse($ini)->format('d/m/Y') . ' a ' . Carbon::parse($fim)->format('d/m/Y') . ').';

            foreach (['dpa_dt_inicio', 'dpa_dt_fim'] as $campo) {
                $d = Carbon::parse($data[$campo])->toDateString();
                if ($d < $ini || $d > $fim) {
                    throw ValidationException::withMessages([$campo => $msg]);
                }
            }
        }

        // 2) A série precisa ter turma REGULAR nesta escola/ano.
        $temTurma = Turma::query()
            ->where('tur_ser_id', $data['dpa_ser_id'])
            ->where('tur_esc_id', $data['dpa_esc_id'])
            ->where('tur_anl_id', $data['dpa_anl_id'])
            ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereNull('tur_deleted_at')
            ->exists();

        if (! $temTurma) {
            throw ValidationException::withMessages(['dpa_ser_id' => 'Série sem turma regular nesta escola/ano.']);
        }

        // 2a) Turma é OPCIONAL: em branco o plano vale para todas as turmas da
        // série; informada, precisa ser uma turma regular daquela série/escola/ano.
        $turId = (int) ($data['dpa_tur_id'] ?? 0);
        if ($turId) {
            $turmaOk = Turma::query()
                ->where('tur_id', $data['dpa_tur_id'])
                ->where('tur_ser_id', $data['dpa_ser_id'])
                ->where('tur_esc_id', $data['dpa_esc_id'])
                ->where('tur_anl_id', $data['dpa_anl_id'])
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                ->whereNull('tur_deleted_at')
                ->exists();

            if (! $turmaOk) {
                throw ValidationException::withMessages([
                    'dpa_tur_id' => 'Turma não pertence à série/escola/ano informados.',
                ]);
            }
        }

        // 3) O professor precisa lecionar a disciplina em alguma turma dessa série.
        $funId = (int) $request->user()->fun_id;
        if (! $funId) {
            throw ValidationException::withMessages(['dpa_ser_id' => 'Seu usuário não possui vínculo de funcionário.']);
        }

        $leciona = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $funId)
            ->where('tp.tup_dis_id', $data['dpa_dis_id'])
            ->where('t.tur_ser_id', $data['dpa_ser_id'])
            ->where('t.tur_esc_id', $data['dpa_esc_id'])
            ->where('t.tur_anl_id', $data['dpa_anl_id'])
            ->where('t.tur_modalidade', Turma::MODALIDADE_REGULAR)
            // Plano de turma específica: precisa reger aquela turma.
            ->when($turId, fn ($q) => $q->where('t.tur_id', $turId))
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->exists();

        if (! $leciona) {
            throw ValidationException::withMessages($turId
                ? ['dpa_tur_id' => 'Você não leciona esta disciplina nesta turma.']
                : ['dpa_ser_id' => 'Você não leciona esta disciplina em turmas desta série.']);
        }

        // 4) Sem outro plano SEU com a mesma série/disciplina e datas idênticas.
        $duplicado = DiarioPlanoAula::query()
            ->where('dpa_user_id', (int) $request->user()->id)
            ->where('dpa_ser_id', $data['dpa_ser_id'])
            ->where('dpa_dis_id', $data['dpa_dis_id'])
            ->whereDate('dpa_dt_inicio', $data['dpa_dt_inicio'])
            ->whereDate('dpa_dt_fim', $data['dpa_dt_fim'])
            // Planos de turmas diferentes (ou "todas") no mesmo período convivem.
            ->when($turId, fn ($q) => $q->where('dpa_tur_id', $turId), fn ($q) => $q->whereNull('dpa_tur_id'))
            ->when($exceto, fn ($q) => $q->where('dpa_id', '!=', $exceto))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages([
                'dpa_dt_inicio' => $turId
                    ? 'Já existe plano para esta turma/disciplina no mesmo período.'
                    : 'Já existe plano para esta série/disciplina no mesmo período.',
            ]);
        }
    }

    private function regravarIndicadores(int $dpaId, $indicadores): void
    {
        DiarioPlanoIndicador::where('dpi_dpa_id', $dpaId)->delete();

        foreach ($indicadores as $indId) {
            DiarioPlanoIndicador::create(['dpi_dpa_id' => $dpaId, 'dpi_ind_id' => $indId]);
        }
    }
}
