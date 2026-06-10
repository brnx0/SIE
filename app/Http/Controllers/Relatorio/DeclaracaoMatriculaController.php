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

class DeclaracaoMatriculaController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/DeclaracaoMatricula/Form', [
            'anoLetivo' => AnoLetivo::where('anl_fl_em_exercicio', true)->first(['anl_id', 'anl_ano']),
            'escolas'   => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola' => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'   => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
        ]);

        $anoLetivo = AnoLetivo::where('anl_fl_em_exercicio', true)->firstOrFail();
        $escola    = Escola::findOrFail($data['esc_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_dt_nascimento,aln_filiacao_1,aln_filiacao_2,aln_mun_id_nasc',
                'aluno.municipioNascimento:mun_id,mun_nome,mun_uf',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno,tur_ser_id,tur_seg_id',
                'turma.serie:ser_id,ser_nome',
                'turma.segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $data['esc_id'])
                ->where('tur_anl_id', $anoLetivo->anl_id)
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                ->when(! empty($data['tur_id']), fn ($q2) => $q2->where('tur_id', $data['tur_id'])))
            ->when(! empty($data['aln_id']), fn ($q) => $q->where('tma_aln_id', $data['aln_id']))
            ->get()
            ->sortBy(fn ($m) => $m->aluno?->aln_nome ?? '')
            ->values();

        $declaracoes = $matriculas->map(function (Matricula $m) {
            $aln   = $m->aluno;
            $turma = $m->turma;

            return [
                'aln_id'         => $aln?->aln_id,
                'nome'           => $aln?->aln_nome,
                'dt_nascimento'  => optional($aln?->aln_dt_nascimento)->format('d/m/Y'),
                'municipio_nasc' => $aln?->municipioNascimento
                    ? ($aln->municipioNascimento->mun_nome . ' - ' . $aln->municipioNascimento->mun_uf)
                    : null,
                'filiacao_1'     => $aln?->aln_filiacao_1,
                'filiacao_2'     => $aln?->aln_filiacao_2,
                'turma_nome'     => $turma?->tur_nome,
                'turma_turno'    => $turma?->tur_turno,
                'serie_nome'     => $turma?->serie?->ser_nome,
                'segmento_nome'  => $turma?->segmento?->seg_nome_reduzido,
            ];
        });

        return Inertia::render('relatorios/DeclaracaoMatricula/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
                'municipio_nome'     => $parametros->municipio?->mun_nome,
                'municipio_uf'       => $parametros->municipio?->mun_uf,
            ] : null,
            'filtros' => [
                'anl_ano'  => $anoLetivo->anl_ano,
                'esc_nome' => $escola->esc_nome,
            ],
            'declaracoes' => $declaracoes,
            'total'       => $declaracoes->count(),
            'dataEmissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
        ]);
    }
}
