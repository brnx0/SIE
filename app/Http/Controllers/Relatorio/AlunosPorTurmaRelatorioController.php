<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use App\Support\XlsxExport;
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
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        ['parametros' => $parametros, 'filtros' => $filtros, 'linhas' => $linhas] = $this->montar($request);

        return Inertia::render('relatorios/AlunosPorTurma/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'total'      => $linhas->count(),
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = ['Turma', 'Nome', 'Sexo', 'Idade', 'Transporte', 'Situação', 'Deficiência', 'Nascimento', 'CPF', 'Cartão SUS', 'Filiação', 'Contato'];

        $rows = $linhas->map(fn ($l) => [
            $l['turma'],
            $l['nome'],
            $l['sexo'],
            $l['idade'],
            $l['usa_transporte'],
            $l['situacao'],
            $l['possui_deficiencia'],
            $l['dt_nascimento'] ? Carbon::parse($l['dt_nascimento'])->format('d/m/Y') : '',
            $l['cpf'],
            $l['cartao_sus'],
            $l['filiacao'],
            $l['contato'],
        ])->all();

        return XlsxExport::download('alunos_por_turma_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /** Rótulo da turma p/ agrupamento/ordenação: "Série - Turma" (ou só a turma). */
    private function rotuloTurma(?Turma $turma): string
    {
        if (! $turma) {
            return '';
        }

        return trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . ($turma->tur_nome ?? ''));
    }

    /**
     * Executa a query e monta linhas/filtros/parametros (compartilhado entre tela e export).
     *
     * @return array{parametros: array|null, filtros: array, linhas: \Illuminate\Support\Collection}
     */
    private function montar(Request $request): array
    {
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
                'turma:tur_id,tur_nome,tur_ser_id,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno',
                'turma.serie:ser_id,ser_nome',
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
                // Ordena primeiro pela turma (série + turma, mesma chave da exibição)
                // e depois pelo nome do aluno.
                $ta = $this->rotuloTurma($a->turma);
                $tb = $this->rotuloTurma($b->turma);
                $c  = strcmp($ta, $tb);
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
                'turma'              => $this->rotuloTurma($m->turma) ?: null,
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

        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        return [
            'parametros' => $parametros?->cabecalhoRelatorio(),
            'filtros' => [
                'anl_id'         => $anoLetivo->anl_id,
                'anl_ano'        => $anoLetivo->anl_ano,
                'esc_id'         => $escola->esc_id,
                'esc_nome'       => $escola->esc_nome,
                'tur_id'         => $data['tur_id'] ?? null,
                'incluir_saidas' => $incluirSaidas,
            ],
            'linhas' => $linhas,
        ];
    }
}
