<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Conceito;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa de Notas (por disciplina).
 * Filtros obrigatórios: escola, turma, disciplina e período (unidade).
 * Exibe, por aluno: cada avaliação/instrumento do período (nota lançada),
 * a média do período, a recuperação (se houver) e a média final (M.F) da unidade.
 *
 * Autorização: professor vê apenas as turmas e disciplinas que leciona;
 * admin/secretaria (acesso por escola) veem tudo dentro da sua abrangência.
 */
class MapaNotasDisciplinaController extends Controller
{
    use NotaLookups;

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/MapaNotasDisciplina/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /**
     * Disciplinas da grade da turma — restritas às do professor quando não é
     * admin nem tem acesso por escola.
     */
    public function disciplinas(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        $serId = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');
        if (! $serId) {
            return response()->json([]);
        }

        $query = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $serId)
            ->where('gd.grd_fl_ativo', true);

        // Professor: só as disciplinas que leciona nesta turma.
        if (! $user->isAdmin() && ! UserAccess::acessoPorEscola($user)) {
            $funId = (int) $user->fun_id;
            if (! $funId) {
                return response()->json([]);
            }
            $query->whereExists(function ($q) use ($turId, $funId) {
                $q->select(DB::raw(1))
                    ->from('edu_turma_professor as tp')
                    ->whereColumn('tp.tup_dis_id', 'd.dis_id')
                    ->where('tp.tup_tur_id', $turId)
                    ->where('tp.tup_fun_id', $funId)
                    ->whereNull('tp.tup_deleted_at');
            });
        }

        return response()->json(
            $query->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get()
        );
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];

        $this->autorizarTurma($turId);
        $this->autorizarDisciplina($turId, $disId);

        $ano       = AnoLetivo::findOrFail($data['anl_id']);
        $escola    = Escola::findOrFail($data['esc_id']);
        $turma     = Turma::with('serie:ser_id,ser_nome')->findOrFail($turId);
        $disciplina = DB::table('edu_disciplina')->where('dis_id', $disId)->first(['dis_id', 'dis_nome']);

        // Rótulo do período + intervalo de datas.
        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_numero', 'uni_tipo', 'uni_dt_inicio', 'uni_dt_fim']);
        $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        $periodoLabel = $uni ? $this->unidadeLabel((int) $uni->uni_numero, (string) $uni->uni_tipo) : '—';
        $periodoDatas = $uni
            ? trim(($fmt($uni->uni_dt_inicio) ?? '') . ($uni->uni_dt_fim ? ' até ' . $fmt($uni->uni_dt_fim) : ''))
            : null;

        // Professor(es) da disciplina na turma.
        $professor = DB::table('edu_turma_professor as tp')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'tp.tup_fun_id')
            ->where('tp.tup_tur_id', $turId)
            ->where('tp.tup_dis_id', $disId)
            ->whereNull('tp.tup_deleted_at')
            ->distinct()
            ->pluck('f.fun_nome')
            ->implode(', ');

        // Avaliações (instrumentos) do período — não-futuras. Regulares viram colunas;
        // as de recuperação alimentam a coluna "Recuperação".
        $hoje = now()->startOfDay();
        $naoFutura = fn ($a) => ! $a->ava_dt || ! Carbon::parse($a->ava_dt)->startOfDay()->gt($hoje);

        $avaliacoes = DiarioAvaliacao::query()
            ->with('instrumento:iav_id,iav_nome')
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->orderBy('ava_dt')
            ->orderBy('ava_id')
            ->get(['ava_id', 'ava_iav_id', 'ava_descricao', 'ava_dt', 'ava_tipo', 'ava_valor', 'ava_fl_recuperacao']);

        $regulares = $avaliacoes->where('ava_fl_recuperacao', false)->filter($naoFutura)->values();
        $recuperacoes = $avaliacoes->where('ava_fl_recuperacao', true)->filter($naoFutura)->values();
        $temRecuperacao = $recuperacoes->isNotEmpty();

        // Notas [ava_id][aln_id] => ['valor','cnc_id'].
        $notaMap = [];
        foreach (DiarioNota::query()->whereIn('nta_ava_id', $avaliacoes->pluck('ava_id')->all() ?: [0])->get(['nta_ava_id', 'nta_aln_id', 'nta_valor', 'nta_cnc_id']) as $n) {
            $notaMap[$n->nta_ava_id][$n->nta_aln_id] = [
                'valor'  => $n->nta_valor === null ? null : (float) $n->nta_valor,
                'cnc_id' => $n->nta_cnc_id,
            ];
        }

        $conceitos = Conceito::query()->pluck('cnc_sigla', 'cnc_id');

        // Média final (M.F) da unidade — autoritativa (inclui recuperação e override manual).
        $mf = CalculoNota::resultado($turId, $disId, $uniId);

        // Cabeçalhos das colunas de instrumentos: nome do instrumento avaliativo
        // (+ descrição/data quando houver) — a nota do aluno vai na célula.
        $instrumentos = $regulares->map(fn ($a, $i) => [
            'ava_id'    => (int) $a->ava_id,
            'descricao' => $this->rotuloAvaliacao($a, $i),
            'valor'     => $a->ava_valor !== null ? $this->numero((float) $a->ava_valor) : null,
            'dt'        => $a->ava_dt ? Carbon::parse($a->ava_dt)->format('d/m') : null,
        ])->values();

        $notaTexto = function ($ava, ?int $alnId) use ($notaMap, $conceitos): string {
            $n = $notaMap[$ava->ava_id][$alnId] ?? null;
            if (! $n) {
                return '—';
            }
            if ($ava->ava_tipo === DiarioAvaliacao::TIPO_NUMERICA) {
                return $n['valor'] === null ? '—' : $this->numero($n['valor']);
            }

            return $n['cnc_id'] !== null ? ($conceitos[$n['cnc_id']] ?? '—') : '—';
        };

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => \Illuminate\Support\Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(function (Matricula $m) use ($regulares, $recuperacoes, $instrumentos, $notaMap, $notaTexto, $mf) {
                $alnId = (int) $m->aluno->aln_id;

                // Notas de cada instrumento (mesma ordem das colunas).
                $notas = $regulares->map(fn ($a) => $notaTexto($a, $alnId))->values();

                // Média do período (sem recuperação) — soma das notas regulares numéricas.
                $numericas = $regulares->where('ava_tipo', DiarioAvaliacao::TIPO_NUMERICA);
                $temNota = false;
                $soma = 0.0;
                foreach ($numericas as $a) {
                    $v = $notaMap[$a->ava_id][$alnId]['valor'] ?? null;
                    if ($v !== null) {
                        $temNota = true;
                        $soma += (float) $v;
                    }
                }
                $media = ($numericas->isNotEmpty() && $temNota) ? $this->numero(round($soma, 2)) : '—';

                // Recuperação — melhor nota numérica de recuperação.
                $rec = null;
                foreach ($recuperacoes as $a) {
                    $v = $notaMap[$a->ava_id][$alnId]['valor'] ?? null;
                    if ($v !== null && ($rec === null || (float) $v > $rec)) {
                        $rec = (float) $v;
                    }
                }
                $recTexto = $rec !== null ? $this->numero($rec) : '—';

                // M.F da unidade (autoritativa).
                $r = $mf[$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                $mfTexto = $this->celula($r)['texto'];

                return [
                    'aln_id'           => $alnId,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'notas'            => $notas,
                    'media'            => $media,
                    'recuperacao'      => $recTexto,
                    'mf'               => $mfTexto,
                ];
            });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/MapaNotasDisciplina/Resultado', [
            'parametros'  => $p ? [
                'nome_entidade'      => $p->par_nome_entidade,
                'msg_cab_secretaria' => $p->par_msg_cab_secretaria,
                'msg_cab_estado'     => $p->par_msg_cab_estado,
                'logomarca_url'      => $p->par_logomarca_url,
                'brasao_url'         => $p->par_brasao_url,
            ] : null,
            'filtros'     => [
                'anl_ano'       => $ano->anl_ano,
                'esc_nome'      => $escola->esc_nome,
                'turma'         => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'disciplina'    => $disciplina->dis_nome ?? '—',
                'professor'     => $professor ?: '—',
                'periodo'       => $periodoLabel,
                'periodo_datas' => $periodoDatas,
            ],
            'instrumentos'    => $instrumentos,
            'tem_recuperacao' => $temRecuperacao,
            'alunos'          => $alunos,
        ]);
    }

    /** Garante que o professor leciona a disciplina na turma (admin/escola liberados). */
    protected function autorizarDisciplina(int $turId, int $disId): void
    {
        $user = auth()->user();
        if ($user->isAdmin() || UserAccess::acessoPorEscola($user)) {
            return;
        }

        $funId = (int) $user->fun_id;
        $leciona = $funId && DB::table('edu_turma_professor')
            ->where('tup_tur_id', $turId)
            ->where('tup_dis_id', $disId)
            ->where('tup_fun_id', $funId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($leciona, 403, 'Você não leciona esta disciplina nesta turma.');
    }

    /**
     * Rótulo da avaliação: nome do instrumento avaliativo, com a descrição livre
     * entre parênteses quando existir. Cai em "Aval. N" se não houver nenhum.
     */
    private function rotuloAvaliacao(DiarioAvaliacao $a, int $i): string
    {
        $nome = trim((string) ($a->instrumento?->iav_nome ?? ''));
        $desc = trim((string) ($a->ava_descricao ?? ''));

        if ($nome !== '' && $desc !== '' && mb_strtolower($nome) !== mb_strtolower($desc)) {
            return $nome . ' (' . $desc . ')';
        }

        return $nome ?: ($desc ?: ('Aval. ' . ($i + 1)));
    }

    /** Formata número no padrão pt-BR (2 casas), removendo casas nulas desnecessárias. */
    private function numero(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
