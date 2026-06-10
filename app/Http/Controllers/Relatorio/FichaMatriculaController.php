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

class FichaMatriculaController extends Controller
{
    private const COR_RACA_MAP = [
        0 => 'Não declarada',
        1 => 'Branca',
        2 => 'Preta',
        3 => 'Parda',
        4 => 'Amarela',
        5 => 'Indígena',
    ];

    private const FILIACAO_TIPO_MAP = [
        1 => 'Mãe',
        2 => 'Pai',
        3 => 'Responsável',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FichaMatricula/Form', [
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
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
        ]);

        $anoLetivo  = AnoLetivo::findOrFail($data['anl_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

        $escola = Escola::with([
            'municipio:mun_id,mun_nome,mun_uf',
            'bairro:bai_id,bai_nome',
        ])->findOrFail($data['esc_id']);

        $matriculas = Matricula::query()
            ->with([
                'aluno',
                'aluno.saude',
                'aluno.municipioNascimento:mun_id,mun_nome,mun_uf',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno,tur_ser_id,tur_seg_id',
                'turma.serie:ser_id,ser_nome',
                'turma.segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->where('tma_tur_id', $data['tur_id'])
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $data['esc_id'])
                ->where('tur_anl_id', $data['anl_id'])
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR))
            ->when(! empty($data['aln_id']), fn ($q) => $q->where('tma_aln_id', $data['aln_id']))
            ->get()
            ->sortBy(fn ($m) => $m->aluno?->aln_nome ?? '')
            ->values();

        $fichas = $matriculas->map(function (Matricula $m) use ($anoLetivo) {
            $aln   = $m->aluno;
            $saude = $aln?->saude;
            $turma = $m->turma;

            $deficiencias = [];
            if ($saude?->als_deficiencias) {
                $deficiencias = array_merge($deficiencias, (array) $saude->als_deficiencias);
            }
            if ($saude?->als_transtornos_globais) {
                $deficiencias = array_merge($deficiencias, (array) $saude->als_transtornos_globais);
            }
            if ($saude?->als_transtornos_aprendizagem) {
                $deficiencias = array_merge($deficiencias, (array) $saude->als_transtornos_aprendizagem);
            }

            return [
                // Dados do aluno
                'nome'             => $aln?->aln_nome,
                'dt_nascimento'    => optional($aln?->aln_dt_nascimento)->format('d/m/Y'),
                'sexo'             => $aln?->aln_sexo,
                'naturalidade'     => $aln?->municipioNascimento
                    ? ($aln->municipioNascimento->mun_nome . ' - ' . $aln->municipioNascimento->mun_uf)
                    : null,
                'nacionalidade'    => $aln?->aln_pais_origem === 'Brasil' ? 'Brasileira' : $aln?->aln_pais_origem,
                'pais_origem'      => $aln?->aln_pais_origem,
                'cor_raca'         => self::COR_RACA_MAP[$aln?->aln_cor_raca ?? -1] ?? null,
                'foto_url'         => $aln?->aln_foto_url,
                'contato_emergencia'  => $saude?->als_contato_emergencia,
                'telefone_emergencia' => $saude?->als_telefone_emergencia,
                // Documentos
                'cpf'              => $aln?->aln_cpf,
                'cd_inep'          => $aln?->aln_cd_inep,
                'nis'              => $aln?->aln_nis,
                'nova_certidao'    => $aln?->aln_nr_certidao,
                'cartao_sus'       => $saude?->als_cartao_sus,
                // Filiação
                'filiacao_1'       => $aln?->aln_filiacao_1,
                'filiacao_1_tipo'  => self::FILIACAO_TIPO_MAP[$aln?->aln_filiacao_1_tipo ?? -1] ?? null,
                'filiacao_2'       => $aln?->aln_filiacao_2,
                'filiacao_2_tipo'  => self::FILIACAO_TIPO_MAP[$aln?->aln_filiacao_2_tipo ?? -1] ?? null,
                // Endereço
                'cep'              => $aln?->aln_cep,
                'logradouro'       => $aln?->aln_logradouro,
                'numero'           => $aln?->aln_numero,
                'complemento'      => $aln?->aln_complemento,
                'bairro'           => $aln?->aln_bairro,
                'cidade'           => $aln?->aln_cidade,
                'uf'               => $aln?->aln_uf,
                // Contato
                'telefone'         => $aln?->aln_telefone,
                'celular'          => $aln?->aln_telefone,
                'email'            => $aln?->aln_email,
                'usa_transporte'   => $aln?->aln_fl_usa_transporte ? 'Sim' : 'Não',
                // Saúde / Deficiência
                'tipo_sanguineo'        => $saude?->als_tipo_sanguineo,
                'plano_saude'           => $saude?->als_plano_saude,
                'alergia'               => $saude?->als_alergia_a,
                'restricoes_alimentares' => $saude?->als_ds_alergias,
                'remedio_febre'         => $saude?->als_remedio_febre,
                'remedio_cefaleia'      => $saude?->als_remedio_cefaleia,
                'patologias'            => array_values(array_filter((array) ($saude?->als_patologias ?? []))),
                'outra_doenca'          => $saude?->als_outra_doenca,
                'patologias_infancia'   => array_values(array_filter((array) ($saude?->als_patologias_infancia ?? []))),
                'outra_doenca_infancia' => $saude?->als_outra_doenca_infancia,
                'clinicas'              => array_values(array_filter((array) ($saude?->als_clinicas ?? []))),
                'recursos_inep'         => array_values(array_filter((array) ($saude?->als_recursos_inep ?? []))),
                'pcd'                   => $saude?->als_fl_pcd ? 'Sim' : 'Não',
                'altas_habilidades'     => $saude?->als_fl_altas_habilidades ? 'Sim' : 'Não',
                'deficiencias'          => array_values(array_filter($deficiencias)),
                'deficiencia_outro'     => $saude?->als_deficiencia_outro,
                'cid'                   => $saude?->als_cid,
                'saude_obs'             => $saude?->als_observacao,
                // Matrícula
                'nr_matricula'     => $aln?->aln_nr_matricula,
                'dt_matricula'     => optional($m->tma_dt_matricula)->format('d/m/Y'),
                'ano_letivo'       => $anoLetivo->anl_ano,
                'serie'            => $turma?->serie?->ser_nome,
                'segmento'         => $turma?->segmento?->seg_nome_completo ?? $turma?->segmento?->seg_nome_reduzido,
                'turma_nome'       => $turma?->tur_nome,
                'turno'            => $turma?->tur_turno,
            ];
        });

        return Inertia::render('relatorios/FichaMatricula/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'escola' => [
                'nome'      => $escola->esc_nome,
                'cd_inep'   => $escola->esc_cd_inep,
                'logradouro' => $escola->esc_logradouro,
                'numero'    => $escola->esc_numero,
                'bairro'    => $escola->bairro?->bai_nome,
                'cidade'    => $escola->municipio?->mun_nome,
                'uf'        => $escola->municipio?->mun_uf,
                'cep'       => $escola->esc_cep,
                'telefone'  => $escola->esc_ddd ? "({$escola->esc_ddd}) {$escola->esc_telefone_fixo}" : $escola->esc_telefone_fixo,
                'email'     => $escola->esc_email,
            ],
            'fichas' => $fichas,
            'total'  => $fichas->count(),
        ]);
    }
}
