<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\StoreJustificativaFaltaRequest;
use App\Http\Requests\Diario\UpdateJustificativaFaltaRequest;
use App\Models\Diario\JustificativaFalta;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\MotivoBaixaFrequencia;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JustificativaFaltaController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        $anoVigente = AnoLetivo::where('anl_fl_em_exercicio', true)->orderByDesc('anl_ano')->first();
        $escDefault = UserAccess::escolaDefault($user);
        $escolas = UserAccess::escolasVisiveis($user);

        $anlId = (int) ($request->input('anl_id') ?: $anoVigente?->anl_id);
        $escId = (int) ($request->input('esc_id') ?: ($escDefault['esc_id'] ?? 0));
        // Só enxerga uma escola → seleciona automaticamente (filtro já aplicado).
        if (! $escId && $escolas->count() === 1) {
            $escId = (int) $escolas->first()->esc_id;
        }
        $turId = (int) $request->input('tur_id');

        // Fluxo: acessa a turma → lista TODOS os alunos ativos → justifica um a um.
        // Cada aluno traz suas justificativas já lançadas (no ano/turma).
        $alunos = collect();
        if ($anlId && $escId && $turId) {
            $porAluno = JustificativaFalta::query()
                ->with('motivo:mbf_id,mbf_descricao')
                ->where('jfa_anl_id', $anlId)
                ->where('jfa_tur_id', $turId)
                ->orderBy('jfa_dt_inicio')
                ->get()
                ->groupBy('jfa_aln_id');

            $alunos = Matricula::query()
                ->where('tma_tur_id', $turId)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
                ->values()
                ->map(fn ($m) => [
                    'aln_id'           => (int) $m->aluno->aln_id,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'justificativas'   => ($porAluno->get($m->aluno->aln_id) ?? collect())->map(fn (JustificativaFalta $j) => [
                        'jfa_id'     => $j->jfa_id,
                        'jfa_mbf_id' => $j->jfa_mbf_id,
                        'motivo'     => $j->motivo?->mbf_descricao,
                        'dt_inicio'  => optional($j->jfa_dt_inicio)->format('Y-m-d'),
                        'dt_fim'     => optional($j->jfa_dt_fim)->format('Y-m-d'),
                        'observacao' => $j->jfa_observacao,
                    ])->values(),
                ]);
        }

        return Inertia::render('secretaria/justificativas-falta/Index', [
            'alunos'  => $alunos,
            'vigente' => $anlId ? $this->unidadeVigente($anlId) : null,
            'filtros' => [
                'anl_id' => $anlId ?: null,
                'esc_id' => $escId ?: null,
                'tur_id' => $turId ?: null,
            ],
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => $escolas,
            'userEscola'  => $escDefault,
            'isAdmin'     => $user->isAdmin(),
            'motivos'     => MotivoBaixaFrequencia::where('mbf_fl_ativo', true)->orderBy('mbf_descricao')->get(['mbf_id', 'mbf_descricao']),
        ]);
    }

    public function store(StoreJustificativaFaltaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertDentroVigente((int) $data['jfa_anl_id'], $data['jfa_dt_inicio'], $data['jfa_dt_fim']);
        $this->assertSemSobreposicao((int) $data['jfa_aln_id'], $data['jfa_dt_inicio'], $data['jfa_dt_fim']);
        $data['jfa_user_id'] = auth()->id();
        JustificativaFalta::create($data);

        return back()->with('success', 'Justificativa registrada com sucesso.');
    }

    public function update(UpdateJustificativaFaltaRequest $request, JustificativaFalta $justificativa): RedirectResponse
    {
        $data = $request->validated();
        $this->assertDentroVigente((int) $data['jfa_anl_id'], $data['jfa_dt_inicio'], $data['jfa_dt_fim']);
        $this->assertSemSobreposicao((int) $data['jfa_aln_id'], $data['jfa_dt_inicio'], $data['jfa_dt_fim'], $justificativa->jfa_id);
        $justificativa->update($data);

        return back()->with('success', 'Justificativa atualizada com sucesso.');
    }

    public function destroy(JustificativaFalta $justificativa): RedirectResponse
    {
        $justificativa->delete();

        return back()->with('success', 'Justificativa removida com sucesso.');
    }

    /**
     * Unidade (trimestre/bimestre) vigente do ano: aquela cuja janela
     * [início, fim + extensão] contém a data de hoje. Null se nenhuma.
     */
    private function unidadeVigente(int $anlId): ?array
    {
        $hoje = now()->toDateString();

        foreach (DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->whereNotNull('uni_dt_inicio')
            ->whereNotNull('uni_dt_fim')
            ->orderBy('uni_numero')
            ->get(['uni_numero', 'uni_tipo', 'uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']) as $u) {
            $ini    = Carbon::parse($u->uni_dt_inicio)->toDateString();
            $fimExt = Carbon::parse($u->uni_dt_fim)->addDays((int) $u->uni_dias_extensao)->toDateString();
            if ($hoje >= $ini && $hoje <= $fimExt) {
                return [
                    'label'     => $u->uni_numero . 'º ' . ucfirst((string) $u->uni_tipo),
                    'dt_inicio' => $ini,
                    'dt_fim'    => $fimExt, // janela já inclui a extensão
                ];
            }
        }

        return null;
    }

    /** Bloqueia justificativa fora do trimestre vigente (não permite período encerrado). */
    private function assertDentroVigente(int $anlId, string $dtInicio, string $dtFim): void
    {
        $vig = $this->unidadeVigente($anlId);
        if (! $vig) {
            throw ValidationException::withMessages([
                'jfa_dt_inicio' => 'Não há trimestre vigente nesta data — não é possível justificar faltas.',
            ]);
        }

        $ini = Carbon::parse($dtInicio)->toDateString();
        $fim = Carbon::parse($dtFim)->toDateString();
        if ($ini < $vig['dt_inicio'] || $fim > $vig['dt_fim']) {
            $br = fn (string $d) => Carbon::parse($d)->format('d/m/Y');
            throw ValidationException::withMessages([
                'jfa_dt_fim' => "O período deve estar dentro do trimestre vigente ({$br($vig['dt_inicio'])} a {$br($vig['dt_fim'])}).",
            ]);
        }
    }

    /** Bloqueia justificativas com período sobreposto para o mesmo aluno (não justificar o mesmo dia 2x). */
    private function assertSemSobreposicao(int $alnId, string $dtInicio, string $dtFim, ?int $ignoreId = null): void
    {
        $ini = Carbon::parse($dtInicio)->toDateString();
        $fim = Carbon::parse($dtFim)->toDateString();

        // Sobreposição: início de uma <= fim da outra E fim de uma >= início da outra.
        $conflito = JustificativaFalta::query()
            ->where('jfa_aln_id', $alnId)
            ->when($ignoreId, fn ($q) => $q->where('jfa_id', '!=', $ignoreId))
            ->whereDate('jfa_dt_inicio', '<=', $fim)
            ->whereDate('jfa_dt_fim', '>=', $ini)
            ->orderBy('jfa_dt_inicio')
            ->first(['jfa_dt_inicio', 'jfa_dt_fim']);

        if ($conflito) {
            $br = fn ($d) => Carbon::parse($d)->format('d/m/Y');
            throw ValidationException::withMessages([
                'jfa_dt_inicio' => "Já existe justificativa para este aluno nesse período ({$br($conflito->jfa_dt_inicio)} a {$br($conflito->jfa_dt_fim)}). Não é possível justificar o mesmo dia novamente.",
            ]);
        }
    }

    /** Turmas REGULAR abertas da escola/ano (lookup do modal). */
    public function turmas(Request $request): JsonResponse
    {
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');
        if (! $anlId || ! $escId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_turma as t')
                ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->where('t.tur_situacao', 'ABERTA')
                ->select('t.tur_id', 't.tur_nome', 's.ser_nome')
                ->distinct()
                ->orderBy('s.ser_nome')
                ->orderBy('t.tur_nome')
                ->get()
        );
    }

    /** Alunos ativos da turma (lookup do modal). */
    public function alunos(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        return response()->json(
            Matricula::query()
                ->where('tma_tur_id', $turId)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with('aluno:aln_id,aln_nome')
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
                ->values()
                ->map(fn ($m) => ['aln_id' => $m->aluno->aln_id, 'aln_nome' => $m->aluno->aln_nome])
        );
    }
}
