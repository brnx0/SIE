<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Diario\DiarioDiagnostico;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatório das Avaliações por Diagnóstico. Por turma + disciplina, agrupa por
 * aluno e, ordenado por indicador, mostra a opção atribuída em CADA período
 * (unidade) daquele indicador. Por aluno ou todos de uma vez.
 *
 * Reusa NotaLookups: turmas (professor → só lotação; admin/secretaria → todas),
 * disciplinas, alunos e autorizarTurma.
 */
class DiagnosticoRelatorioController extends Controller
{
    use NotaLookups;

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/Diagnostico/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);
        $this->autorizarDisciplina((int) $data['tur_id'], (int) $data['dis_id']);

        $ano     = AnoLetivo::findOrFail($data['anl_id']);
        $escola  = Escola::findOrFail($data['esc_id']);
        $turma   = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);
        $disNome = (string) DB::table('edu_disciplina')->where('dis_id', $data['dis_id'])->value('dis_nome');
        $alnId   = ! empty($data['aln_id']) ? (int) $data['aln_id'] : null;

        // Indicadores da série/disciplina (mesmo conjunto do lançamento — sem filtro de ano).
        $indicadores = DB::table('edu_serie_indicador')
            ->where('ind_ser_id', $turma->tur_ser_id)
            ->where('ind_dis_id', $data['dis_id'])
            ->where('ind_fl_ativo', true)
            ->whereNull('ind_deleted_at')
            ->orderBy('ind_id')
            ->get(['ind_id', 'ind_descricao']);

        // Períodos (unidades) do ano → colunas.
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo)]);
        $uniIds = $unidades->pluck('uni_id')->all();

        // Alunos (ATIVA) da turma — 1 selecionado ou todos.
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->when($alnId, fn ($q) => $q->where('tma_aln_id', $alnId))
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values();

        // Valores: [aln_id][ind_id][uni_id] => opcao.
        $valores = DiarioDiagnostico::query()
            ->where('dgn_tur_id', $turma->tur_id)
            ->where('dgn_dis_id', $data['dis_id'])
            ->when($alnId, fn ($q) => $q->where('dgn_aln_id', $alnId))
            ->get(['dgn_aln_id', 'dgn_ind_id', 'dgn_uni_id', 'dgn_opcao']);

        $map = [];
        foreach ($valores as $v) {
            $map[(int) $v->dgn_aln_id][(int) $v->dgn_ind_id][(int) $v->dgn_uni_id] = $v->dgn_opcao;
        }

        $alunosPayload = $alunos->map(function ($m) use ($indicadores, $uniIds, $map) {
            $alnId = (int) $m->aluno->aln_id;

            return [
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'linhas'           => $indicadores->map(fn ($ind) => [
                    'ind_descricao' => $ind->ind_descricao,
                    'valores'       => array_map(fn ($u) => $map[$alnId][(int) $ind->ind_id][$u] ?? null, $uniIds),
                ])->values(),
            ];
        })->values();

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/Diagnostico/Resultado', [
            'parametros' => $p ? [
                'nome_entidade'      => $p->par_nome_entidade,
                'msg_cab_secretaria' => $p->par_msg_cab_secretaria,
                'msg_cab_estado'     => $p->par_msg_cab_estado,
                'municipio'          => $p->municipio_label,
                'logomarca_url'      => $p->par_logomarca_url,
                'brasao_url'         => $p->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'    => $ano->anl_ano,
                'esc_nome'   => $escola->esc_nome,
                'turma'      => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'disciplina' => $disNome,
                'aluno'      => $alnId ? ($alunos->first()?->aluno?->aln_nome) : null,
            ],
            'unidades' => $unidades->values(),
            'opcoes'   => DiarioDiagnostico::OPCOES,
            'alunos'   => $alunosPayload,
        ]);
    }

    /** Professor (não admin/secretaria) só pode emitir disciplina que leciona na turma. */
    private function autorizarDisciplina(int $turId, int $disId): void
    {
        $user = auth()->user();
        if ($user->isAdmin() || UserAccess::acessoPorEscola($user)) {
            return;
        }
        $funId   = (int) $user->fun_id;
        $leciona = $funId && DB::table('edu_turma_professor')
            ->where('tup_fun_id', $funId)
            ->where('tup_tur_id', $turId)
            ->where('tup_dis_id', $disId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($leciona, 403, 'Você não leciona esta disciplina nesta turma.');
    }
}
