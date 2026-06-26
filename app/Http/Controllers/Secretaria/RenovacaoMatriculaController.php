<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interesse de renovação de matrícula: a secretaria marca, por aluno ativo,
 * se ele tem interesse em renovar para o ano seguinte. Persiste em
 * edu_turma_aluno.tma_fl_renovado.
 */
class RenovacaoMatriculaController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('secretaria/renovacao-matricula/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Turmas do ano/escola + segmentos presentes (p/ o filtro). */
    public function turmas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);
        $this->autorizarEscola((int) $data['esc_id']);

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('seg.seg_ordem')
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 's.ser_nome', 's.seg_id', 'seg.seg_nome_reduzido']);

        $segmentos = $turmas
            ->filter(fn ($t) => $t->seg_id !== null)
            ->unique('seg_id')
            ->sortBy('seg_nome_reduzido')
            ->map(fn ($t) => ['seg_id' => (int) $t->seg_id, 'seg_nome' => $t->seg_nome_reduzido])
            ->values();

        return response()->json([
            'segmentos' => $segmentos,
            'turmas'    => $turmas->map(fn ($t) => [
                'tur_id'   => (int) $t->tur_id,
                'turma'    => $t->tur_nome,
                'serie'    => $t->ser_nome,
                'seg_id'   => $t->seg_id !== null ? (int) $t->seg_id : null,
                'segmento' => $t->seg_nome_reduzido,
            ])->values(),
        ]);
    }

    /** Alunos ativos da turma + flag de interesse em renovar. */
    public function alunos(Request $request): JsonResponse
    {
        $data = $request->validate(['tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id']]);

        $tur = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id']);
        abort_unless($tur, 404);
        $this->autorizarEscola((int) $tur->tur_esc_id);

        $alunos = Matricula::query()
            ->where('tma_tur_id', $data['tur_id'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            // Mantém a matrícula ATIVA mais recente por aluno (ex.: saiu e voltou pra mesma turma).
            ->sortByDesc(fn ($m) => $m->tma_id)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn (Matricula $m) => [
                'aln_id'    => (int) $m->aluno->aln_id,
                'nome'      => $m->aluno->aln_nome,
                'matricula' => $m->aluno->aln_nr_matricula,
                // Tristate: null = não informado, true = quer, false = não quer.
                'renovar'   => $m->tma_fl_renovado === null ? null : (bool) $m->tma_fl_renovado,
            ]);

        return response()->json(['alunos' => $alunos]);
    }

    /** Marca/desmarca o interesse em renovar de 1 aluno. */
    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'  => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id'  => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'renovar' => ['required', 'boolean'],
        ]);

        $tur = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id']);
        abort_unless($tur, 404);
        $this->autorizarEscola((int) $tur->tur_esc_id);

        // Matrícula ATIVA mais recente do aluno na turma (cobre o caso de saída e retorno).
        $mat = Matricula::where('tma_tur_id', $data['tur_id'])
            ->where('tma_aln_id', $data['aln_id'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->orderByDesc('tma_id')
            ->first(['tma_id']);
        abort_unless($mat, 404);

        $mat->update(['tma_fl_renovado' => (bool) $data['renovar']]);

        return response()->json(['ok' => true]);
    }

    private function autorizarEscola(int $escId): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $ids = UserAccess::escolasIds($user);
        abort_unless(is_array($ids) && in_array($escId, $ids, true), 403, 'Você não tem acesso a esta escola.');
    }
}
