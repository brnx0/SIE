<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DadosAlunosTurmaController extends Controller
{
    private const COR_RACA_MAP = [
        0 => 'Não declarada', 1 => 'Branca', 2 => 'Preta',
        3 => 'Parda', 4 => 'Amarela', 5 => 'Indígena',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/DadosAlunosTurma/Form', [
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
            'seg_id' => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
            'ser_id' => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'tur_id' => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $anoLetivo    = AnoLetivo::findOrFail($data['anl_id']);
        $parametros   = ParametroEntidade::first();
        $escolaFiltro = ! empty($data['esc_id']) ? Escola::find($data['esc_id']) : null;
        $dtCorte      = $anoLetivo->anl_dt_corte;

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula,aln_dt_nascimento,aln_sexo,aln_cor_raca,'
                    . 'aln_cpf,aln_cd_inep,aln_nr_certidao,aln_filiacao_1,aln_filiacao_2,'
                    . 'aln_cep,aln_logradouro,aln_numero,aln_complemento,aln_bairro,aln_cidade,aln_uf',
                'aluno.saude:als_id,als_aln_id,als_cartao_sus',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_seg_id,tur_ser_id',
                'turma.escola:esc_id,esc_nome',
                'turma.serie:ser_id,ser_nome',
                'turma.segmento:seg_id,seg_nome_reduzido',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->where('tma_anl_id', $data['anl_id'])
            ->when(! empty($data['esc_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_esc_id', $data['esc_id'])))
            ->when(! empty($data['seg_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_seg_id', $data['seg_id'])))
            ->when(! empty($data['ser_id']), fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('tur_ser_id', $data['ser_id'])))
            ->when(! empty($data['tur_id']), fn ($q) => $q->where('tma_tur_id', $data['tur_id']))
            ->get();

        $linhas = $matriculas->map(function (Matricula $m) use ($dtCorte) {
            $aln   = $m->aluno;
            $turma = $m->turma;

            $idade = null;
            if ($dtCorte && $aln?->aln_dt_nascimento) {
                $nasc  = Carbon::parse($aln->aln_dt_nascimento);
                $corte = Carbon::parse($dtCorte);
                $idade = $corte->year - $nasc->year;
                if ($corte->format('m-d') < $nasc->format('m-d')) $idade--;
            }

            $serie    = $turma?->serie?->ser_nome;
            $turNome  = $turma?->tur_nome;
            $turmaCol = trim(($serie ? "$serie " : '') . ($turNome ?? ''));

            return [
                'escola'        => $turma?->escola?->esc_nome,
                'turma'         => $turmaCol !== '' ? $turmaCol : null,
                'aln_nome'      => $aln?->aln_nome,
                'mat_aluno'     => $aln?->aln_nr_matricula,
                'dt_nascimento' => optional($aln?->aln_dt_nascimento)->format('d/m/Y'),
                'sexo'          => $aln?->aln_sexo,
                'idade'         => $idade,
                'etnia'         => self::COR_RACA_MAP[$aln?->aln_cor_raca ?? -1] ?? null,
                'cpf'           => $this->formatCpf($aln?->aln_cpf),
                'cd_inep'       => $aln?->aln_cd_inep,
                'nr_certidao'   => $aln?->aln_nr_certidao,
                'cartao_sus'    => $m->aluno?->saude?->als_cartao_sus,
                'filiacao_1'    => $aln?->aln_filiacao_1,
                'filiacao_2'    => $aln?->aln_filiacao_2,
                'endereco'      => $this->montarEndereco($aln),
                '_sortEsc'      => $turma?->escola?->esc_nome ?? '',
                '_sortTur'      => $turmaCol,
                '_sortAln'      => $aln?->aln_nome ?? '',
            ];
        })
            ->sortBy([['_sortEsc', 'asc'], ['_sortTur', 'asc'], ['_sortAln', 'asc']])
            ->values()
            ->map(function ($r) {
                unset($r['_sortEsc'], $r['_sortTur'], $r['_sortAln']);
                return $r;
            });

        return Inertia::render('relatorios/DadosAlunosTurma/Resultado', [
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
            'total'     => $linhas->count(),
        ]);
    }

    private function formatCpf(?string $cpf): ?string
    {
        if (! $cpf) return null;
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return $cpf;
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
    }

    private function montarEndereco($aln): ?string
    {
        if (! $aln) return null;
        $rua = trim(implode(', ', array_filter([$aln->aln_logradouro, $aln->aln_numero])));
        $rua .= $aln->aln_complemento ? ' - ' . $aln->aln_complemento : '';
        $cidUf = trim(implode('/', array_filter([$aln->aln_cidade, $aln->aln_uf])));
        $cep   = $aln->aln_cep ? 'CEP ' . $aln->aln_cep : null;
        $partes = array_filter([$rua ?: null, $aln->aln_bairro, $cidUf ?: null, $cep]);
        return $partes ? implode(' — ', $partes) : null;
    }
}
