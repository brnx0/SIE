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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $alnId = (int) $request->input('aln_id');

        $justificativas = collect();
        if ($anlId && $escId) {
            $justificativas = JustificativaFalta::query()
                ->with(['aluno:aln_id,aln_nome,aln_nr_matricula', 'motivo:mbf_id,mbf_descricao'])
                ->where('jfa_anl_id', $anlId)
                ->where('jfa_esc_id', $escId)
                ->when($turId, fn ($q) => $q->where('jfa_tur_id', $turId))
                ->when($alnId, fn ($q) => $q->where('jfa_aln_id', $alnId))
                ->orderByDesc('jfa_dt_inicio')
                ->get()
                ->map(fn (JustificativaFalta $j) => [
                    'jfa_id'           => $j->jfa_id,
                    'jfa_esc_id'       => $j->jfa_esc_id,
                    'jfa_tur_id'       => $j->jfa_tur_id,
                    'jfa_aln_id'       => $j->jfa_aln_id,
                    'jfa_mbf_id'       => $j->jfa_mbf_id,
                    'aluno'            => $j->aluno?->aln_nome,
                    'aln_nr_matricula' => $j->aluno?->aln_nr_matricula,
                    'motivo'           => $j->motivo?->mbf_descricao,
                    'dt_inicio'        => optional($j->jfa_dt_inicio)->format('Y-m-d'),
                    'dt_fim'           => optional($j->jfa_dt_fim)->format('Y-m-d'),
                    'observacao'       => $j->jfa_observacao,
                ]);
        }

        return Inertia::render('secretaria/justificativas-falta/Index', [
            'justificativas' => $justificativas,
            'filtros'        => [
                'anl_id' => $anlId ?: null,
                'esc_id' => $escId ?: null,
                'tur_id' => $turId ?: null,
                'aln_id' => $alnId ?: null,
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
        $data['jfa_user_id'] = auth()->id();
        JustificativaFalta::create($data);

        return back()->with('success', 'Justificativa registrada com sucesso.');
    }

    public function update(UpdateJustificativaFaltaRequest $request, JustificativaFalta $justificativa): RedirectResponse
    {
        $justificativa->update($request->validated());

        return back()->with('success', 'Justificativa atualizada com sucesso.');
    }

    public function destroy(JustificativaFalta $justificativa): RedirectResponse
    {
        $justificativa->delete();

        return back()->with('success', 'Justificativa removida com sucesso.');
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
