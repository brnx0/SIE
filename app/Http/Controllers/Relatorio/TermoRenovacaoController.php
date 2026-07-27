<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Termo de Renovação — documento formal por aluno ou em lote (turma/escola),
 * para alunos com interesse de renovação de matrícula registrado
 * (edu_turma_aluno.tma_fl_renovado = true), gerado para o ano seguinte.
 *
 * Reaproveita o padrão da Declaração de Matrícula: Inertia puro, sem blade/mPDF;
 * a impressão/PDF é do navegador (window.print) sobre o #print-area, uma folha
 * por aluno. LAYOUT do documento a ajustar conforme o modelo oficial.
 */
class TermoRenovacaoController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/TermoRenovacao/Form', [
            'anosLetivos' => AnoLetivo::query()
                ->whereNull('anl_deleted_at')
                ->orderByDesc('anl_ano')
                ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            'isAdmin'    => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id' => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
        ]);

        $anoLetivo  = AnoLetivo::findOrFail($data['anl_id']);
        $escola     = Escola::with('municipio:mun_id,mun_nome,mun_uf')->findOrFail($data['esc_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        // Endereço da escola em linha única (logradouro, nº, complemento).
        $endereco = collect([$escola->esc_logradouro, $escola->esc_numero, $escola->esc_complemento])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->implode(' - ');

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_dt_nascimento,aln_nr_matricula,aln_filiacao_1,aln_filiacao_2,aln_mun_id_nasc',
                'aluno.municipioNascimento:mun_id,mun_nome,mun_uf',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno,tur_ser_id,tur_seg_id',
                'turma.serie:ser_id,ser_nome',
                'turma.segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
                'serie:ser_id,ser_nome',
            ])
            // Todos os alunos com matrícula ativa — o termo é o documento que o
            // responsável assina para confirmar a renovação (a flag é resultado
            // desse processo, não um pré-requisito para emitir).
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
            $serie = $m->serie_efetiva;

            return [
                'aln_id'         => $aln?->aln_id,
                'nome'           => $aln?->aln_nome,
                'matricula'      => $aln?->aln_nr_matricula,
                'dt_nascimento'  => optional($aln?->aln_dt_nascimento)->format('d/m/Y'),
                'municipio_nasc' => $aln?->municipioNascimento
                    ? ($aln->municipioNascimento->mun_nome . ' - ' . $aln->municipioNascimento->mun_uf)
                    : null,
                'filiacao_1'     => $aln?->aln_filiacao_1,
                'filiacao_2'     => $aln?->aln_filiacao_2,
                'turma_nome'     => $turma?->tur_nome,
                'turma_turno'    => $turma?->tur_turno,
                'serie_nome'     => $serie?->ser_nome,
                'segmento_nome'  => $turma?->segmento?->seg_nome_completo ?: $turma?->segmento?->seg_nome_reduzido,
            ];
        });

        return Inertia::render('relatorios/TermoRenovacao/Resultado', [
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
                'anl_ano'      => $anoLetivo->anl_ano,
                'anl_ano_prox' => $anoLetivo->anl_ano + 1,
                'esc_nome'     => $escola->esc_nome,
            ],
            'escola' => [
                'nome'      => $escola->esc_nome,
                'endereco'  => $endereco ?: null,
                'zona'      => $escola->esc_zona === 'R' ? 'Rural' : ($escola->esc_zona === 'U' ? 'Urbana' : null),
                'municipio' => $escola->municipio
                    ? trim($escola->municipio->mun_nome . ($escola->municipio->mun_uf ? ' (' . $escola->municipio->mun_uf . ')' : ''))
                    : null,
                'codigo'    => $escola->esc_cd_escola ?: $escola->esc_cd_inep,
            ],
            'declaracoes' => $declaracoes,
            'total'       => $declaracoes->count(),
            'dataEmissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
        ]);
    }
}
