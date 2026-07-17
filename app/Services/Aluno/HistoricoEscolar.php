<?php

namespace App\Services\Aluno;

use App\Models\Aluno\Aluno;
use App\Models\Aluno\HistoricoManual;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use App\Support\CalculoEncerramento;
use App\Support\CalculoNota;
use Illuminate\Support\Facades\DB;

/**
 * Monta os dados do Histórico Escolar / Certificado de Conclusão de um aluno,
 * emitido por uma escola. Regras em docs/regras/historico_escolar.md.
 *
 * - Só entram anos com APROVAÇÃO ({6,7,8,40}).
 * - MF anual por componente = CalculoNota::consolidar (diário do professor ou
 *   lançamento manual da secretaria; média dos períodos arredondada ao 0,5).
 * - Aprovado pelo Conselho (saída 7): MF abaixo da média mínima é exibida COMO a
 *   média mínima, igual à Ata Final. Só apresentação — a nota real fica no banco.
 * - A escola emissora não é escolhida: é a do último ano escolar do aluno (maior
 *   ano letivo aprovado). Anos manuais posteriores a esse ano não entram.
 */
class HistoricoEscolar
{
    /** Situações de saída consideradas aprovação. */
    public const APROVADO = [6, 7, 8, 40];

    /** Cache da média mínima por "anl_id:esc_id" (o mesmo ano se repete entre componentes). */
    private array $mediasMinimas = [];

    public function gerar(int $alnId): array
    {
        $aluno = Aluno::with('municipioNascimento:mun_id,mun_nome,mun_uf')->findOrFail($alnId);

        // Matrículas REGULAR aprovadas do aluno (qualquer escola da rede).
        $matriculas = Matricula::query()
            ->where('tma_aln_id', $alnId)
            ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereIn('tma_tas_cod_saida', self::APROVADO)
            ->whereNull('tma_deleted_at')
            ->with([
                'turma:tur_id,tur_esc_id,tur_ser_id,tur_seg_id,tur_anl_id',
                'turma.escola:esc_id,esc_nome,esc_mun_id',
                'turma.segmento:seg_id,seg_nome_completo,seg_nome_reduzido',
                'turma.anoLetivo:anl_id,anl_ano',
                'turma.serie:ser_id,ser_nome,ser_ordem_no_segmento,ser_carga_horaria',
                'serie:ser_id,ser_nome,ser_ordem_no_segmento,ser_carga_horaria',
                'situacaoSaida:tas_cod,tas_descricao',
            ])
            ->get()
            // Série efetiva: matrícula (tma_ser_id) ou, quando nula, a da turma.
            ->filter(fn (Matricula $m) => $m->turma && $m->serieEfetiva && $m->turma->anoLetivo);

        abort_if(
            $matriculas->isEmpty(),
            422,
            'O aluno não possui ano escolar aprovado — não há histórico a emitir.'
        );

        // A emissora é a escola do último ano escolar do aluno: maior ano letivo e,
        // no mesmo ano, a série mais adiantada (desempate final pela matrícula mais nova).
        $ultimoAno = $matriculas
            ->sortBy(fn (Matricula $m) => [
                (int) $m->turma->anoLetivo->anl_ano,
                (int) ($m->serieEfetiva->ser_ordem_no_segmento ?? 0),
                (int) $m->tma_id,
            ])
            ->last();

        $anoCorte = (int) $ultimoAno->turma->anoLetivo->anl_ano;
        $emissora = Escola::with('municipio:mun_id,mun_nome,mun_uf', 'bairro:bai_id,bai_nome')
            ->findOrFail((int) $ultimoAno->turma->tur_esc_id);

        // Ordena por ordem da série no segmento, depois ano letivo.
        $matriculas = $matriculas
            ->sortBy(fn (Matricula $m) => sprintf('%04d-%04d', $m->serieEfetiva->ser_ordem_no_segmento ?? 0, $m->turma->anoLetivo->anl_ano))
            ->values();

        // Anos do SISTEMA (matrículas) normalizados como itens de ano escolar.
        $itens = $matriculas->map(function (Matricula $m) use ($alnId) {
            $dados = $this->anoEscolar($m, $alnId);

            return [
                'ordem'         => $m->serieEfetiva->ser_ordem_no_segmento ?? 0,
                'ano'           => (int) $m->turma->anoLetivo->anl_ano,
                'ano_escolar'   => $m->serieEfetiva->ser_nome,
                'frequencia'    => $dados['frequencia'],
                'dias_letivos'  => $dados['dias_letivos'],
                'carga_horaria' => $m->serieEfetiva->ser_carga_horaria,
                'situacao'      => $m->situacaoSaida?->tas_descricao ?? 'Aprovado(a)',
                'curso'         => $m->turma->segmento?->seg_nome_completo ?? $m->turma->segmento?->seg_nome_reduzido,
                'estabelecimento' => $m->turma->escola?->esc_nome,
                'municipio_uf'  => $this->municipioUf($m->turma->escola?->esc_mun_id),
                'componentes'   => $dados['componentes'],
            ];
        })->all();

        // Anos MANUAIS (escolaridade anterior / fora da rede) — até o ano de corte.
        $manuais = HistoricoManual::where('hmn_aln_id', $alnId)
            ->with([
                'disciplinas.disciplina:dis_id,dis_nome',
                'disciplinas.conceito:cnc_id,cnc_sigla',
                'serie:ser_id,ser_nome,ser_ordem_no_segmento,ser_carga_horaria,seg_id',
                'serie.segmento:seg_id,seg_nome_completo,seg_nome_reduzido',
                'escola:esc_id,esc_nome,esc_mun_id',
                'situacao:tas_cod,tas_descricao',
            ])->get();

        foreach ($manuais as $h) {
            if (! $h->serie || ! $h->hmn_ano) {
                continue;
            }
            $ano = (int) $h->hmn_ano;
            if ($ano > $anoCorte) {
                continue;
            }
            $itens[] = [
                'ordem'         => $h->serie->ser_ordem_no_segmento ?? 0,
                'ano'           => $ano,
                'ano_escolar'   => $h->serie->ser_nome,
                'frequencia'    => $h->hmn_frequencia !== null ? (int) round((float) $h->hmn_frequencia) : null,
                'dias_letivos'  => $h->hmn_dias_letivos,
                'carga_horaria' => $h->hmn_carga_horaria ?? $h->serie->ser_carga_horaria,
                'situacao'      => $h->situacao?->tas_descricao ?? 'Aprovado(a)',
                'curso'         => $h->serie->segmento?->seg_nome_completo ?? $h->serie->segmento?->seg_nome_reduzido,
                'estabelecimento' => $h->escola?->esc_nome ?? $h->hmn_esc_nome,
                'municipio_uf'  => $h->escola ? $this->municipioUf($h->escola->esc_mun_id) : $this->municipioUf($h->hmn_mun_id),
                'componentes'   => $h->disciplinas->map(fn ($d) => [
                    'dis_id'     => (int) $d->hmd_dis_id,
                    'nome'       => $d->disciplina?->dis_nome ?? '—',
                    'ordem'      => (int) $d->hmd_ordem,
                    'mf'         => $d->hmd_cnc_id
                        ? ($d->conceito?->cnc_sigla ?? '-')
                        : ($d->hmd_mf !== null ? number_format((float) $d->hmd_mf, 1, ',', '') : '-'),
                    'conceitual' => (bool) $d->hmd_cnc_id,
                ])->all(),
            ];
        }

        // Ordena todos os anos (sistema + manual) por ordem da série, depois ano.
        usort($itens, fn ($a, $b) => [$a['ordem'], $a['ano']] <=> [$b['ordem'], $b['ano']]);

        // Colunas (anos escolares) + matriz de componentes.
        $colunas    = [];
        $componentes = [];  // dis_id => ['nome'=>, 'ordem'=>]
        $matriz      = [];  // dis_id => [colIndex => celula string]
        $relacao     = [];
        $conceitual  = false;

        foreach ($itens as $i => $it) {
            foreach ($it['componentes'] as $dis) {
                $componentes[$dis['dis_id']] ??= ['nome' => $dis['nome'], 'ordem' => $dis['ordem']];
                if ($dis['ordem'] < $componentes[$dis['dis_id']]['ordem']) {
                    $componentes[$dis['dis_id']]['ordem'] = $dis['ordem'];
                }
                $matriz[$dis['dis_id']][$i] = $dis['mf'];
                if (! empty($dis['conceitual'])) $conceitual = true;
            }

            $colunas[] = [
                'ano_escolar'   => $it['ano_escolar'],
                'frequencia'    => $it['frequencia'],
                'dias_letivos'  => $it['dias_letivos'],
                'carga_horaria' => $it['carga_horaria'],
                'situacao'      => $it['situacao'],
            ];

            $relacao[] = [
                'etapa'           => $it['ano_escolar'],
                'ano_conclusao'   => $it['ano'],
                'curso'           => $it['curso'],
                'estabelecimento' => $it['estabelecimento'],
                'municipio_uf'    => $it['municipio_uf'],
            ];
        }

        // Ordena componentes por ordem, depois nome; monta linhas da matriz.
        uasort($componentes, fn ($a, $b) => [$a['ordem'], $a['nome']] <=> [$b['ordem'], $b['nome']]);

        $linhasMatriz = [];
        foreach ($componentes as $disId => $c) {
            $celulas = [];
            foreach (array_keys($colunas) as $col) {
                $celulas[] = $matriz[$disId][$col] ?? '-';
            }
            $linhasMatriz[] = ['componente' => $c['nome'], 'celulas' => $celulas];
        }

        // Último ano na escola emissora (texto da certificação).
        $ultima = $matriculas->last();

        return [
            'escola'    => $this->dadosEscola($emissora),
            'aluno'     => [
                'nome'         => $aluno->aln_nome,
                'dt_nascimento' => optional($aluno->aln_dt_nascimento)->format('d/m/Y'),
                'naturalidade' => $this->municipioUf($aluno->aln_mun_id_nasc),
                'filiacao_1'   => $aluno->aln_filiacao_1,
                'filiacao_2'   => $aluno->aln_filiacao_2,
            ],
            'conclusao' => [
                'ano_escolar' => $ultima?->serieEfetiva->ser_nome,
                'segmento'    => $ultima?->turma->segmento?->seg_nome_completo,
            ],
            'colunas'     => $colunas,
            'componentes' => $linhasMatriz,
            'relacao'     => $relacao,
            'conceitual'  => $conceitual,
            'legenda'     => DB::table('cfg_conceito')->orderBy('cnc_peso', 'desc')
                ->get(['cnc_sigla', 'cnc_descricao'])->all(),
            'emissao'     => [
                'municipio_uf' => $this->municipioUf($emissora->esc_mun_id),
                'data'         => now()->format('d/m/Y'),
            ],
        ];
    }

    /**
     * Anos APROVADOS calculados pelo sistema, já consolidados (disciplinas + MF),
     * p/ exibição read-only na tela de lançamento manual. Não editáveis.
     *
     * @return array<int, array{anl_id:int, anl_ano:int, serie:?string, escola:?string, situacao:string, disciplinas:array}>
     */
    public function sistemaConsolidado(int $alnId): array
    {
        $matriculas = Matricula::query()
            ->where('tma_aln_id', $alnId)
            ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereIn('tma_tas_cod_saida', self::APROVADO)
            ->whereNull('tma_deleted_at')
            ->with([
                'turma:tur_id,tur_esc_id,tur_ser_id,tur_seg_id,tur_anl_id',
                'turma.escola:esc_id,esc_nome',
                'turma.anoLetivo:anl_id,anl_ano',
                'turma.serie:ser_id,ser_nome,ser_ordem_no_segmento,ser_carga_horaria',
                'serie:ser_id,ser_nome,ser_ordem_no_segmento,ser_carga_horaria',
                'situacaoSaida:tas_cod,tas_descricao',
            ])
            ->get()
            ->filter(fn (Matricula $m) => $m->turma && $m->serieEfetiva && $m->turma->anoLetivo);

        return $matriculas
            ->sortBy(fn (Matricula $m) => sprintf('%04d-%04d', $m->serieEfetiva->ser_ordem_no_segmento ?? 0, $m->turma->anoLetivo->anl_ano))
            ->map(function (Matricula $m) use ($alnId) {
                $dados = $this->anoEscolar($m, $alnId);

                return [
                    'anl_id'      => (int) $m->turma->tur_anl_id,
                    'anl_ano'     => (int) $m->turma->anoLetivo->anl_ano,
                    'serie'       => $m->serieEfetiva->ser_nome,
                    'escola'      => $m->turma->escola?->esc_nome,
                    'situacao'    => $m->situacaoSaida?->tas_descricao ?? 'Aprovado(a)',
                    'disciplinas' => collect($dados['componentes'])
                        ->map(fn ($c) => ['nome' => $c['nome'], 'mf' => $c['mf']])
                        ->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** MF por componente + resumo (frequência/dias letivos) de uma matrícula/ano. */
    private function anoEscolar(Matricula $m, int $alnId): array
    {
        $turId = (int) $m->turma->tur_id;
        $anlId = (int) $m->turma->anoLetivo->anl_id;

        // Aprovado pelo Conselho: nas disciplinas abaixo da média mínima, a MF sai
        // como a própria média — mesma regra da Ata Final (AtaFinalController).
        $conselho = (int) $m->tma_tas_cod_saida === CalculoEncerramento::CONSELHO;
        $mediaRef = $conselho ? $this->mediaMinima($anlId, (int) $m->turma->tur_esc_id) : null;

        $uniIds = DB::table('cfg_unidade')->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')->pluck('uni_id')->map(fn ($v) => (int) $v)->all();

        // Disciplinas da grade da série NO ANO LETIVO da matrícula (a grade muda
        // de ano p/ ano — sem o filtro por ano, mistura grades de anos diferentes).
        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $m->serieEfetiva->ser_id)
            ->where('gd.grd_anl_id', $anlId)
            ->where('gd.grd_fl_ativo', true)
            ->distinct()
            ->orderBy('gd.grd_ordem')->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome', 'gd.grd_ordem']);

        $recs = CalculoNota::recuperacoesFinais($turId);

        $componentes = [];
        foreach ($disciplinas as $d) {
            $disId = (int) $d->dis_id;
            $porUni = [];
            foreach ($uniIds as $u) {
                $porUni[$u] = CalculoNota::resultado($turId, $disId, $u)[$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
            }
            $consol = CalculoNota::consolidar($porUni, $recs[$disId][$alnId] ?? null);

            if ($mediaRef !== null && $consol['tipo'] === 'numerica' && $consol['valor'] !== null
                && (float) $consol['valor'] < $mediaRef) {
                $consol['valor'] = $mediaRef;
            }

            $componentes[] = [
                'dis_id'     => $disId,
                'nome'       => $d->dis_nome,
                'ordem'      => (int) $d->grd_ordem,
                'mf'         => $this->formatarMf($consol),
                'conceitual' => $consol['tipo'] === 'conceitual',
            ];
        }

        // Dias letivos do ano (soma do mapa mês→dias).
        $dl = DB::table('cfg_dias_letivos')->where('dlt_anl_id', $anlId)->value('dlt_meses');
        $diasLetivos = 0;
        if ($dl) {
            $meses = is_array($dl) ? $dl : json_decode($dl, true);
            $diasLetivos = (int) array_sum(array_map('intval', (array) $meses));
        }

        // Faltas do ano: média das faltas por disciplina (proxy p/ faltas-dia).
        // TODO: validar fonte definitiva de frequência com a secretaria.
        $totalFaltas = (int) DB::table('edu_nota_manual')
            ->where('nmn_tur_id', $turId)->where('nmn_aln_id', $alnId)
            ->whereNull('nmn_deleted_at')->sum('nmn_faltas');
        $nDis = max(1, count($disciplinas));
        $faltasDia = (int) round($totalFaltas / $nDis);

        $frequencia = $diasLetivos > 0
            ? max(0, min(100, (int) round((1 - $faltasDia / $diasLetivos) * 100)))
            : 0;

        return [
            'componentes'  => $componentes,
            'dias_letivos' => $diasLetivos,
            'frequencia'   => $frequencia,
        ];
    }

    /**
     * Média mínima de aprovação: da ESCOLA (cfg_media_escola) ou, na falta, a da
     * REDE (ano letivo). `null` quando nenhuma das duas está configurada — aí não
     * há régua e a MF real é preservada.
     */
    private function mediaMinima(int $anlId, int $escId): ?float
    {
        $chave = "{$anlId}:{$escId}";

        if (! array_key_exists($chave, $this->mediasMinimas)) {
            $mde = DB::table('cfg_media_escola')
                ->where('mde_anl_id', $anlId)->where('mde_esc_id', $escId)
                ->value('mde_media');
            $rede = DB::table('cfg_ano_letivo')->where('anl_id', $anlId)->value('anl_media_geral');

            $media = $mde ?? $rede;
            $this->mediasMinimas[$chave] = $media !== null ? (float) $media : null;
        }

        return $this->mediasMinimas[$chave];
    }

    /** Formata a MF consolidada: numérica "5,0" · conceito "H" · vazio "-". */
    private function formatarMf(array $consol): string
    {
        if ($consol['tipo'] === null) return '-';
        if ($consol['conceito'] && ($consol['conceito']['sigla'] ?? null)) {
            return $consol['conceito']['sigla'];
        }
        if ($consol['valor'] !== null) {
            return number_format((float) $consol['valor'], 1, ',', '');
        }
        return '-';
    }

    private function municipioUf(?int $munId): ?string
    {
        if (! $munId) return null;
        $m = DB::table('edu_municipio')->where('mun_id', $munId)->first(['mun_nome', 'mun_uf']);
        return $m ? "{$m->mun_nome}/{$m->mun_uf}" : null;
    }

    private function dadosEscola(Escola $e): array
    {
        return [
            'nome'            => $e->esc_nome,
            'municipio_uf'    => $this->municipioUf($e->esc_mun_id),
            'endereco'        => trim(implode(', ', array_filter([
                $e->esc_logradouro, $e->esc_numero, $e->bairro?->bai_nome,
            ]))),
            'telefone'        => $e->esc_telefone_fixo,
            // Ato autorizativo — labels sempre exibidos no template, valor pode ser nulo.
            'resolucao_num'   => $e->esc_resolucao_num,
            'portaria_num'    => $e->esc_cme_portaria_num,
            'dt_publicacao'   => optional($e->esc_dt_publicacao)->format('d/m/Y'),
            'fundamentacao'   => $e->esc_fundamentacao_legal,
        ];
    }
}
