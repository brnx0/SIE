<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AlunosPorTurmaRelatorioController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlunosPorTurma/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_dt_corte']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $componente = 'relatorios/AlunosPorTurma/Resultado';
        $data = $request->validate([
            'anl_id'           => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'           => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id'           => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'incluir_saidas'   => ['sometimes', 'boolean'],
        ]);

        $incluirSaidas = $request->boolean('incluir_saidas');

        $anoLetivo = AnoLetivo::findOrFail($data['anl_id']);
        $escola    = Escola::findOrFail($data['esc_id']);
        $dtCorte   = $anoLetivo->anl_dt_corte;

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_sexo,aln_dt_nascimento,aln_cpf,aln_filiacao_1,aln_filiacao_2,aln_telefone,aln_email,aln_fl_usa_transporte',
                'aluno.saude:als_id,als_aln_id,als_cartao_sus,als_fl_pcd,als_fl_altas_habilidades,als_deficiencias,als_transtornos_globais,als_transtornos_aprendizagem',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno',
                'situacaoEntrada:tas_cod,tas_descricao_enturmacao',
                'situacaoSaida:tas_cod,tas_descricao_enturmacao',
            ])
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $data['esc_id'])
                ->where('tur_anl_id', $data['anl_id'])
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                ->where('tur_situacao', 'ABERTA')
                ->when(! empty($data['tur_id']), fn ($q2) => $q2->where('tur_id', $data['tur_id'])))
            ->when(! $incluirSaidas, fn ($q) => $q->where('tma_situacao', Matricula::SITUACAO_ATIVA))
            ->get()
            ->sort(function ($a, $b) {
                $c = strcmp($a->turma?->tur_nome ?? '', $b->turma?->tur_nome ?? '');
                return $c !== 0 ? $c : strcmp($a->aluno?->aln_nome ?? '', $b->aluno?->aln_nome ?? '');
            })
            ->values();

        $linhas = $matriculas->map(function (Matricula $m) use ($dtCorte) {
            $aln   = $m->aluno;
            $saude = $aln?->saude;

            $idade = null;
            if ($dtCorte && $aln?->aln_dt_nascimento) {
                $nasc = Carbon::parse($aln->aln_dt_nascimento);
                $ref  = Carbon::parse($dtCorte);
                $idade = $ref->year - $nasc->year;
                if ($ref->format('m-d') < $nasc->format('m-d')) $idade--;
            }

            $possuiDef = false;
            if ($saude) {
                $possuiDef = $saude->als_fl_pcd
                    || $saude->als_fl_altas_habilidades
                    || ! empty($saude->als_deficiencias)
                    || ! empty($saude->als_transtornos_globais)
                    || ! empty($saude->als_transtornos_aprendizagem);
            }

            $filiacao = trim(implode(' / ', array_filter([$aln?->aln_filiacao_1, $aln?->aln_filiacao_2])));
            $contato  = $aln?->aln_telefone ?: $aln?->aln_email ?: null;

            $situacao = $m->tma_situacao === Matricula::SITUACAO_ATIVA
                ? ($m->situacaoEntrada?->tas_descricao_enturmacao ?? 'Matriculado(a)')
                : ($m->situacaoSaida?->tas_descricao_enturmacao ?? $m->tma_situacao);

            return [
                'turma'              => $m->turma?->tur_nome,
                'tur_id'             => $m->turma?->tur_id,
                'aln_id'             => $aln?->aln_id,
                'nome'               => $aln?->aln_nome,
                'sexo'               => $aln?->aln_sexo,
                'idade'              => $idade,
                'usa_transporte'     => $aln?->aln_fl_usa_transporte ? 'Sim' : 'Não',
                'situacao'           => $situacao,
                'possui_deficiencia' => $possuiDef ? 'Sim' : 'Não',
                'dt_nascimento'      => optional($aln?->aln_dt_nascimento)->format('Y-m-d'),
                'cpf'                => $aln?->aln_cpf,
                'cartao_sus'         => $saude?->als_cartao_sus,
                'filiacao'           => $filiacao ?: null,
                'contato'            => $contato,
            ];
        });

        $parametros = ParametroEntidade::first();

        return Inertia::render($componente, [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_id'         => $anoLetivo->anl_id,
                'anl_ano'        => $anoLetivo->anl_ano,
                'esc_id'         => $escola->esc_id,
                'esc_nome'       => $escola->esc_nome,
                'tur_id'         => $data['tur_id'] ?? null,
                'incluir_saidas' => $incluirSaidas,
            ],
            'linhas' => $linhas,
            'total'  => $linhas->count(),
        ]);
    }
}
