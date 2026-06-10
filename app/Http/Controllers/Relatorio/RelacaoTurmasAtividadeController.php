<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RelacaoTurmasAtividadeController extends Controller
{
    private const DIAS_MAP = [
        'dom' => 'Dom', 'seg' => 'Seg', 'ter' => 'Ter', 'qua' => 'Qua',
        'qui' => 'Qui', 'sex' => 'Sex', 'sab' => 'Sáb',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/RelacaoTurmasAtividade/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $anoLetivo    = AnoLetivo::findOrFail($data['anl_id']);
        $parametros   = ParametroEntidade::first();
        $escolaFiltro = ! empty($data['esc_id']) ? Escola::find($data['esc_id']) : null;

        $turmas = Turma::query()
            ->with([
                'escola:esc_id,esc_nome',
                'atividades.atividade:atv_id,atv_descricao',
            ])
            ->where('tur_modalidade', Turma::MODALIDADE_ATIVIDADE)
            ->where('tur_anl_id', $data['anl_id'])
            ->when(! empty($data['esc_id']), fn ($q) => $q->where('tur_esc_id', $data['esc_id']))
            ->withCount([
                'matriculas as qtd_alunos' => fn ($q) => $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->where('tma_modalidade', Turma::MODALIDADE_ATIVIDADE),
            ])
            ->get();

        $linhas = $turmas->map(function (Turma $t) {
            $dias = collect((array) ($t->tur_dias_funcionamento ?? []))
                ->map(fn ($d) => self::DIAS_MAP[$d] ?? $d)
                ->implode(', ');

            $atividades = $t->atividades
                ->map(fn ($a) => $a->atividade?->atv_descricao)
                ->filter()
                ->implode(', ');

            return [
                'cd_inep'    => $t->tur_cd_inep,
                'escola'     => $t->escola?->esc_nome,
                'turma'      => $t->tur_nome,
                'turno'      => $t->tur_turno,
                'atividades' => $atividades ?: null,
                'dias'       => $dias ?: null,
                'qtd_alunos' => (int) $t->qtd_alunos,
            ];
        })->sortBy([['escola', 'asc'], ['turma', 'asc']])->values();

        return Inertia::render('relatorios/RelacaoTurmasAtividade/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'anoLetivo' => ['anl_id' => $anoLetivo->anl_id, 'anl_ano' => $anoLetivo->anl_ano],
            'escola'    => $escolaFiltro ? ['esc_id' => $escolaFiltro->esc_id, 'esc_nome' => $escolaFiltro->esc_nome] : null,
            'linhas'    => $linhas,
            'total_turmas' => $linhas->count(),
            'total_alunos' => $linhas->sum('qtd_alunos'),
        ]);
    }
}
