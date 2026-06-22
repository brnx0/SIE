<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Diario\AeeAvaliacao;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DesempenhoAeeController extends Controller
{
    private const SITUACAO_LABEL = [
        Matricula::SITUACAO_ATIVA       => 'Ativa',
        Matricula::SITUACAO_CANCELADA   => 'Cancelada',
        Matricula::SITUACAO_TRANSFERIDA => 'Transferida',
        Matricula::SITUACAO_FALECIDO    => 'Falecido',
        Matricula::SITUACAO_EVADIDO     => 'Evadido',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/DesempenhoAee/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Lookup de turmas AEE por ano letivo + escola (para o seletor do formulário). */
    public function turmas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        return response()->json(
            Turma::query()
                ->where('tur_modalidade', Turma::MODALIDADE_AEE)
                ->where('tur_anl_id', $data['anl_id'])
                ->when(! empty($data['esc_id']), fn ($q) => $q->where('tur_esc_id', $data['esc_id']))
                ->orderBy('tur_nome')
                ->get(['tur_id', 'tur_nome', 'tur_esc_id'])
        );
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $anoLetivo    = AnoLetivo::findOrFail($data['anl_id']);
        $parametros   = ParametroEntidade::first();
        $escolaFiltro = ! empty($data['esc_id']) ? Escola::find($data['esc_id']) : null;

        $turmas = Turma::query()
            ->with('escola:esc_id,esc_nome')
            ->where('tur_modalidade', Turma::MODALIDADE_AEE)
            ->where('tur_anl_id', $data['anl_id'])
            ->when(! empty($data['esc_id']), fn ($q) => $q->where('tur_esc_id', $data['esc_id']))
            ->when(! empty($data['tur_id']), fn ($q) => $q->where('tur_id', $data['tur_id']))
            ->orderBy('tur_nome')
            ->get();

        $turIds = $turmas->pluck('tur_id')->all();

        // Avaliações de todas as turmas do relatório, agrupadas por (turma, aluno).
        $avaliacoesPorChave = AeeAvaliacao::query()
            ->whereIn('dav_tur_id', $turIds ?: [0])
            ->orderBy('dav_dt')
            ->orderBy('dav_id')
            ->get(['dav_tur_id', 'dav_aln_id', 'dav_dt', 'dav_descricao'])
            ->groupBy(fn ($a) => $a->dav_tur_id.'-'.$a->dav_aln_id);

        $linhas = $turmas->map(function (Turma $t) use ($avaliacoesPorChave) {
            $alunos = Matricula::query()
                ->where('tma_tur_id', $t->tur_id)
                ->where('tma_modalidade', Turma::MODALIDADE_AEE)
                ->whereNull('tma_deleted_at')
                ->where(function ($q) {
                    $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                      ->orWhereNotNull('tma_tas_cod_saida');
                })
                ->with(['aluno:aln_id,aln_nome,aln_nr_matricula', 'situacaoSaida:tas_cod,tas_descricao'])
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->unique(fn ($m) => $m->aluno->aln_id)
                ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
                ->values()
                ->map(function (Matricula $m) use ($t, $avaliacoesPorChave) {
                    $avs = ($avaliacoesPorChave[$t->tur_id.'-'.$m->aluno->aln_id] ?? collect())
                        ->map(fn ($a) => [
                            'dt'        => Carbon::parse($a->dav_dt)->toDateString(),
                            'descricao' => $a->dav_descricao,
                        ])->values();

                    return [
                        'aln_id'     => $m->aluno->aln_id,
                        'nome'       => $m->aluno->aln_nome,
                        'matricula'  => $m->aluno->aln_nr_matricula,
                        'situacao'   => $this->situacaoLabel($m),
                        'avaliacoes' => $avs,
                    ];
                });

            return [
                'tur_id'  => $t->tur_id,
                'turma'   => $t->tur_nome,
                'escola'  => $t->escola?->esc_nome,
                'alunos'  => $alunos,
            ];
        })->filter(fn ($l) => $l['alunos']->isNotEmpty())->values();

        return Inertia::render('relatorios/DesempenhoAee/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'anoLetivo'    => ['anl_id' => $anoLetivo->anl_id, 'anl_ano' => $anoLetivo->anl_ano],
            'escola'       => $escolaFiltro ? ['esc_id' => $escolaFiltro->esc_id, 'esc_nome' => $escolaFiltro->esc_nome] : null,
            'linhas'       => $linhas,
            'total_turmas' => $linhas->count(),
            'total_alunos' => $linhas->sum(fn ($l) => $l['alunos']->count()),
        ]);
    }

    private function situacaoLabel(Matricula $m): string
    {
        if ($m->tma_situacao === Matricula::SITUACAO_ATIVA) {
            return 'Ativa';
        }

        return $m->situacaoSaida?->tas_descricao
            ?? self::SITUACAO_LABEL[$m->tma_situacao]
            ?? (string) $m->tma_situacao;
    }
}
