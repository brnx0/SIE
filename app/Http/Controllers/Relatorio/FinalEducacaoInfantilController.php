<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatório Final da Educação Infantil — por turma (1 por página): matrícula,
 * nome, data de nascimento e situação final do aluno. Cobre as turmas dos
 * segmentos de Educação Infantil (Creche e Pré-escola). Filtro por ano +
 * escola e/ou turma; vazio gera todas as turmas visíveis ao usuário.
 */
class FinalEducacaoInfantilController extends Controller
{
    /** Situações de saída consideradas aprovação (vide CalculoEncerramento): 6 Aprovado, 7 Apr. Conselho. */
    private const APROVADO = [6, 7];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FinalEducacaoInfantil/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Turmas de Educação Infantil do ano (e da escola, se informada). */
    public function turmas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);

        $turmas = $this->turmasEduInfantil((int) $data['anl_id'], [(int) $data['esc_id']])
            ->map(fn ($t) => [
                'tur_id'    => (int) $t->tur_id,
                'turma'     => $this->rotuloTurma($t),
                'escola'    => $t->esc_nome,
                'encerrada' => $t->tur_situacao === 'ENCERRADA',
            ])
            ->values();

        return response()->json(['turmas' => $turmas]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);
        $ano = AnoLetivo::findOrFail($data['anl_id']);

        // Sempre dentro da escola informada (autorizada); tur_id, se vier, restringe a 1 turma dela.
        $linhas = $this->turmasEduInfantil(
            (int) $data['anl_id'],
            [(int) $data['esc_id']],
            ! empty($data['tur_id']) ? (int) $data['tur_id'] : null,
        );

        $turmas = $linhas->map(fn ($t) => [
            'tur_id'   => (int) $t->tur_id,
            'escola'   => $t->esc_nome,
            'turma'    => $this->rotuloTurma($t),
            'turno'    => $t->tur_turno,
            'segmento' => $t->seg_nome_reduzido,
            'alunos'   => $this->alunosDaTurma((int) $t->tur_id),
        ])->values();

        $p       = ParametroEntidade::first();
        $escNome = optional(DB::table('edu_escola')->where('esc_id', $data['esc_id'])->first(['esc_nome']))->esc_nome;

        return Inertia::render('relatorios/FinalEducacaoInfantil/Resultado', [
            'parametros' => $p ? [
                'nome_entidade'      => $p->par_nome_entidade,
                'msg_cab_secretaria' => $p->par_msg_cab_secretaria,
                'msg_cab_estado'     => $p->par_msg_cab_estado,
                'logomarca_url'      => $p->par_logomarca_url,
                'brasao_url'         => $p->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'  => $ano->anl_ano,
                'esc_nome' => $escNome,
                'turma'    => ! empty($data['tur_id']) ? ($turmas->first()['turma'] ?? null) : null,
            ],
            'turmas' => $turmas,
        ]);
    }

    // ============ Helpers ============

    /** Alunos da turma (1 por aluno, mais recente) com situação final pela enturmação. */
    private function alunosDaTurma(int $turId): Collection
    {
        return Matricula::query()
            ->where('tma_tur_id', $turId)
            ->whereNull('tma_deleted_at')
            ->semRemanejado()
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula,aln_dt_nascimento', 'situacaoSaida:tas_cod,tas_descricao,tas_descricao_enturmacao')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortByDesc(fn ($m) => $m->tma_id)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function (Matricula $m) {
                $cod = $m->tma_tas_cod_saida !== null ? (int) $m->tma_tas_cod_saida : null;
                if ($cod !== null && in_array($cod, self::APROVADO, true)) {
                    // Educação Infantil: aprovação é registrada como "Concluiu".
                    $situacao = 'Concluiu';
                } elseif ($cod !== null) {
                    $situacao = $m->situacaoSaida?->tas_descricao_enturmacao ?: $m->situacaoSaida?->tas_descricao;
                } else {
                    $situacao = null;
                }

                return [
                    'aln_nr_matricula'  => $m->aluno->aln_nr_matricula,
                    'aln_nome'          => $m->aluno->aln_nome,
                    'aln_dt_nascimento' => $m->aluno->aln_dt_nascimento?->format('d/m/Y'),
                    'situacao'          => $situacao ?: '—',
                ];
            });
    }

    /**
     * Turmas REGULAR de Educação Infantil (Creche/Pré-escola) do ano.
     *
     * @param  int[]|null  $escolasIds  null = todas (admin); array = filtra escolas.
     */
    private function turmasEduInfantil(int $anlId, ?array $escolasIds, ?int $turId = null): Collection
    {
        $q = DB::table('edu_turma as t')
            ->join('edu_segmento as seg', 'seg.seg_id', '=', 't.tur_seg_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $anlId);

        if ($turId !== null) {
            $q->where('t.tur_id', $turId);
        }
        if ($escolasIds !== null) {
            $q->whereIn('t.tur_esc_id', $escolasIds);
        }

        return $q->orderBy('e.esc_nome')->orderBy('seg.seg_ordem')->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_esc_id', 't.tur_nome', 't.tur_turno', 't.tur_situacao', 's.ser_nome', 'seg.seg_nome_reduzido', 'e.esc_nome'])
            ->filter(fn ($t) => $this->isEducacaoInfantil($t->seg_nome_reduzido))
            ->values();
    }

    /** Educação Infantil = Creche, "Educação Infantil" ou Pré-escola (sem acento, maiúsculo). */
    private function isEducacaoInfantil(?string $segNome): bool
    {
        if ($segNome === null) {
            return false;
        }
        $n = mb_strtoupper(Str::ascii($segNome), 'UTF-8');

        // PRE + ESCOL (e não só "PRE") evita falso-positivo do tipo "PREPARATÓRIO".
        return str_contains($n, 'CRECHE')
            || str_contains($n, 'INFANTIL')
            || (str_contains($n, 'PRE') && str_contains($n, 'ESCOL'));
    }

    private function rotuloTurma(object $t): string
    {
        return trim(($t->ser_nome ? $t->ser_nome . ' - ' : '') . $t->tur_nome);
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
