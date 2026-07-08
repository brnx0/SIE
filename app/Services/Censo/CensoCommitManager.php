<?php

namespace App\Services\Censo;

use App\Models\Censo\CensoLote;
use App\Models\Censo\CensoRegistro;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fase B (commit) — M2: escolas (reg00) e turmas (reg20).
 * Insert-only: registro já existente (casado por INEP) nunca é sobrescrito.
 * Resolve município por IBGE, bairro find-or-create, série via de-para de etapa.
 * Ver docs/regras/importacao_censo.md.
 */
class CensoCommitManager
{
    /** @var array<string,int> IBGE => mun_id */
    private array $municipioCache = [];

    /** @var array<string,int> "mun_id|nome" => bai_id */
    private array $bairroCache = [];

    /** @var array<int,object> ced_codigo => linha de-para */
    private array $etapaCache = [];

    /** @var array<string,int> esc_cd_inep => esc_mun_id (fallback naturalidade) */
    private array $escolaMunCache = [];

    /** @var array<string,bool> INEP de pessoas que são aluno (reg60) */
    private array $alunoInepSet = [];

    /** @var array<string,bool> INEP de pessoas que são funcionário (reg40/50) */
    private array $funcInepSet = [];

    /** @var array<string,bool> aln_cd_inep já existentes */
    private array $alunoExistente = [];

    /** @var array<string,bool> fun_cd_censo já existentes */
    private array $funcExistente = [];

    /** @var array<string,bool> CPFs já usados (aluno) */
    private array $cpfAlunoUsado = [];

    /** @var array<string,bool> CPFs já usados (funcionário) */
    private array $cpfFuncUsado = [];

    /** @var array<string,bool> pares "esc:seg" cujo vínculo edu_escola_segmento já foi garantido */
    private array $escolaSegmentoCache = [];

    /** @return array<string,int> resumo */
    public function commit(CensoLote $lote): array
    {
        if (! $lote->icl_anl_id) {
            throw new RuntimeException('Lote sem ano letivo destino.');
        }

        $this->carregarCaches($lote);

        $resumo = [
            'escolas_criadas'      => 0,
            'escolas_ignoradas'    => 0,
            'escolas_erro'         => 0,
            'turmas_criadas'       => 0,
            'turmas_ignoradas'     => 0,
            'turmas_bloqueadas'    => 0,
            'turmas_erro'          => 0,
            'alunos_criados'       => 0,
            'alunos_ignorados'     => 0,
            'funcionarios_criados' => 0,
            'funcionarios_ignorados' => 0,
            'saude_criada'         => 0,
            'pessoas_sem_vinculo'  => 0,
            'pessoas_erro'         => 0,
            'matriculas_criadas'   => 0,
            'matriculas_ignoradas' => 0,
            'matriculas_sem_turma' => 0,
            'matriculas_sem_aluno' => 0,
            'lotacoes_criadas'     => 0,
            'lotacoes_ignoradas'   => 0,
            'lotacoes_sem_dep'     => 0,
            'lotacoes_sem_cargo'   => 0,
            'admissoes_criadas'    => 0,
            'turma_prof_criados'   => 0,
            'turma_prof_bloqueados' => 0,
            'turma_prof_sem_turma' => 0,
        ];

        $this->commitEscolas($lote, $resumo);
        $this->commitTurmas($lote, $resumo);
        $this->commitPessoas($lote, $resumo);
        $this->commitMatriculas($lote, $resumo);
        $this->commitLotacoes($lote, $resumo);
        $this->commitTurmaProfessor($lote, $resumo);

        $lote->update(['icl_status' => 'COMMITADO']);

        return $resumo;
    }

    private function carregarCaches(CensoLote $lote): void
    {
        $this->municipioCache = DB::table('edu_municipio')
            ->pluck('mun_id', 'mun_codigo_ibge')
            ->map(fn ($v) => (int) $v)->all();

        $this->etapaCache = DB::table('cfg_censo_etapa_depara')
            ->get()->keyBy('ced_codigo')->all();

        $this->escolaMunCache = DB::table('edu_escola')
            ->pluck('esc_mun_id', 'esc_cd_inep')
            ->map(fn ($v) => (int) $v)->all();

        // Classificação por vínculo (do staging deste lote).
        $this->alunoInepSet = $this->inepSetFromStaging($lote, '60', 'inep_aluno');
        $this->funcInepSet = $this->inepSetFromStaging($lote, '50', 'inep_docente')
            + $this->inepSetFromStaging($lote, '40', 'inep_gestor');

        // Existentes na base (insert-only).
        $this->alunoExistente = DB::table('edu_aluno')->whereNotNull('aln_cd_inep')
            ->pluck('aln_cd_inep')->flip()->map(fn () => true)->all();
        $this->funcExistente = DB::table('edu_funcionario')->whereNotNull('fun_cd_censo')
            ->pluck('fun_cd_censo')->flip()->map(fn () => true)->all();
        $this->cpfAlunoUsado = DB::table('edu_aluno')->whereNotNull('aln_cpf')
            ->pluck('aln_cpf')->flip()->map(fn () => true)->all();
        $this->cpfFuncUsado = DB::table('edu_funcionario')->whereNotNull('fun_cpf')
            ->pluck('fun_cpf')->flip()->map(fn () => true)->all();
    }

    /** @return array<string,bool> conjunto de INEPs distintos de um tipo no staging */
    private function inepSetFromStaging(CensoLote $lote, string $tipo, string $chaveJson): array
    {
        $valores = DB::table('imp_censo_registro')
            ->where('icr_icl_id', $lote->icl_id)
            ->where('icr_tipo', $tipo)
            ->select(DB::raw("icr_dados->>'{$chaveJson}' as v"))
            ->pluck('v')
            ->filter()
            ->all();

        $set = [];
        foreach ($valores as $v) {
            $set[(string) $v] = true;
        }

        return $set;
    }

    // ---------- Escolas (reg 00) ----------

    private function commitEscolas(CensoLote $lote, array &$resumo): void
    {
        $registros = CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)
            ->where('icr_tipo', '00')
            ->get();

        foreach ($registros as $reg) {
            $campos = $reg->icr_campos;
            $inep = $this->col($campos, 2);

            if ($inep === '') {
                $this->marcarErro($reg, 'INEP da escola ausente.');
                $resumo['escolas_erro']++;
                continue;
            }

            $existe = DB::table('edu_escola')->where('esc_cd_inep', $inep)->exists();
            if ($existe) {
                $resumo['escolas_ignoradas']++;
                continue;
            }

            try {
                $munId = $this->resolverMunicipio($this->col($campos, 8));
                if ($munId === null) {
                    $this->marcarErro($reg, 'Município (IBGE '.$this->col($campos, 8).') não encontrado.');
                    $resumo['escolas_erro']++;
                    continue;
                }

                $nome = $this->col($campos, 6) ?: 'ESCOLA SEM NOME';
                $baiId = $this->findOrCreateBairro($munId, $this->col($campos, 13) ?: 'NÃO INFORMADO');
                // Situação de funcionamento (censo): 1=Em atividade, 2=Paralisada, 3=Extinta.
                // Só "Em atividade" entra como ativa no sistema.
                $situacao = (int) ($this->col($campos, 3) ?: 1);
                $now = now();

                DB::table('edu_escola')->insert([
                    'esc_cd_inep'               => $inep,
                    'esc_cnpj'                  => null,
                    'esc_nome'                  => mb_substr($nome, 0, 150),
                    'esc_apelido'               => mb_substr($nome, 0, 100),
                    'esc_cep'                   => $this->cep($this->col($campos, 7)),
                    'esc_logradouro'            => mb_substr($this->col($campos, 10) ?: 'NÃO INFORMADO', 0, 150),
                    'esc_numero'                => mb_substr($this->col($campos, 11) ?: 'SN', 0, 10),
                    'esc_complemento'           => mb_substr($this->col($campos, 12), 0, 100) ?: null,
                    'esc_bai_id'                => $baiId,
                    'esc_mun_id'                => $munId,
                    'esc_zona'                  => $this->zona($this->col($campos, 19)),
                    'esc_localizacao_dif'       => (int) ($this->col($campos, 20) ?: 0),
                    'esc_ddd'                   => mb_substr($this->col($campos, 14), 0, 2) ?: null,
                    'esc_telefone_fixo'         => mb_substr($this->col($campos, 15), 0, 11) ?: null,
                    'esc_email'                 => mb_substr($this->col($campos, 17), 0, 150),
                    'esc_dep_administrativa'    => (int) ($this->col($campos, 21) ?: 0),
                    'esc_situacao_func'         => $situacao,
                    'esc_regulamentada_conselho' => $this->col($campos, 36) === '1',
                    'esc_fl_ativo'              => $situacao === 1,
                    'esc_created_at'            => $now,
                    'esc_updated_at'            => $now,
                ]);

                $resumo['escolas_criadas']++;
            } catch (\Throwable $e) {
                $this->marcarErro($reg, 'Falha ao inserir escola: '.$e->getMessage());
                $resumo['escolas_erro']++;
            }
        }
    }

    // ---------- Turmas (reg 20) ----------

    private function commitTurmas(CensoLote $lote, array &$resumo): void
    {
        $registros = CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)
            ->where('icr_tipo', '20')
            ->get();

        foreach ($registros as $reg) {
            $campos = $reg->icr_campos;
            $inepTurma = $this->col($campos, 4);
            $inepEscola = $this->col($campos, 2);

            if ($inepTurma === '') {
                $this->marcarErro($reg, 'INEP da turma ausente.');
                $resumo['turmas_erro']++;
                continue;
            }

            $jaExiste = DB::table('edu_turma')
                ->where('tur_cd_inep', $inepTurma)
                ->whereNull('tur_deleted_at')
                ->exists();
            if ($jaExiste) {
                $resumo['turmas_ignoradas']++;
                continue;
            }

            $escId = DB::table('edu_escola')->where('esc_cd_inep', $inepEscola)->value('esc_id');
            if (! $escId) {
                $this->marcarErro($reg, "Escola INEP {$inepEscola} não encontrada p/ a turma.");
                $resumo['turmas_erro']++;
                continue;
            }

            // De-para de etapa → segmento + série
            $codEtapa = (int) $this->col($campos, 26);
            $etapa = $this->etapaCache[$codEtapa] ?? null;

            if ($etapa === null) {
                $this->marcarErro($reg, "Etapa Educacenso {$codEtapa} sem de-para.");
                $resumo['turmas_bloqueadas']++;
                continue;
            }
            if ($etapa->ced_fl_ignorar) {
                $resumo['turmas_ignoradas']++; // atividade complementar/AEE — não vira turma regular aqui
                continue;
            }
            if (! $etapa->ced_ser_id) {
                $this->marcarErro($reg, "Etapa {$codEtapa} sem série mapeada.");
                $resumo['turmas_bloqueadas']++;
                continue;
            }

            // Segmento sai do de-para se informado, senão da própria série (edu_serie.seg_id).
            $segId = $etapa->ced_seg_id
                ?: DB::table('edu_serie')->where('ser_id', $etapa->ced_ser_id)->value('seg_id');
            if (! $segId) {
                $this->marcarErro($reg, "Série {$etapa->ced_ser_id} sem segmento (edu_serie.seg_id).");
                $resumo['turmas_bloqueadas']++;
                continue;
            }

            try {
                $horario = $this->col($campos, 8);
                $now = now();

                // Sem vínculo escola↔segmento, os lookups da tela de turma ficam vazios
                // (série/segmento "em branco" ao editar). Garante o vínculo do segmento.
                $this->garantirEscolaSegmento((int) $escId, (int) $segId, (int) $lote->icl_anl_id, $now);

                DB::table('edu_turma')->insert([
                    'tur_esc_id'             => $escId,
                    'tur_anl_id'             => $lote->icl_anl_id,
                    'tur_seg_id'             => $segId,
                    'tur_ser_id'             => $etapa->ced_ser_id,
                    'tur_cd_inep'            => $inepTurma,
                    'tur_nome'               => mb_substr($this->col($campos, 5) ?: 'TURMA', 0, 20),
                    'tur_turno'              => $this->turno($horario),
                    'tur_tipo_atendimento'   => 'NÃO SE APLICA',
                    'tur_situacao'           => 'ABERTA',
                    'tur_hora_inicio'        => $this->hora($horario, 0),
                    'tur_hora_fim'           => $this->hora($horario, 1),
                    'tur_mediacao'           => $this->col($campos, 6) ?: null,
                    'tur_modalidade'         => 'REGULAR',
                    'tur_local_diferenciado' => $this->col($campos, 23) ?: null,
                    'tur_dias_funcionamento' => json_encode($this->dias($campos), JSON_UNESCAPED_UNICODE),
                    'tur_created_at'         => $now,
                    'tur_updated_at'         => $now,
                ]);

                $resumo['turmas_criadas']++;
            } catch (\Throwable $e) {
                $this->marcarErro($reg, 'Falha ao inserir turma: '.$e->getMessage());
                $resumo['turmas_erro']++;
            }
        }
    }

    /**
     * Garante o vínculo escola↔segmento (edu_escola_segmento) para o ano.
     * Sem ele, os lookups da tela de turma (segmento → série) ficam vazios.
     * Cobre a faixa completa de séries do segmento (primeira à última).
     */
    private function garantirEscolaSegmento(int $escId, int $segId, int $anlId, $now): void
    {
        $chave = "{$escId}:{$segId}";
        if (isset($this->escolaSegmentoCache[$chave])) {
            return;
        }
        $this->escolaSegmentoCache[$chave] = true;

        $existe = DB::table('edu_escola_segmento')
            ->where('esc_id', $escId)
            ->where('seg_id', $segId)
            ->whereNull('esg_deleted_at')
            ->exists();
        if ($existe) {
            return;
        }

        // Faixa de séries do segmento (ordenadas). ser_id_inicio/fim são NOT NULL.
        $series = DB::table('edu_serie')
            ->where('seg_id', $segId)
            ->orderBy('ser_ordem_no_segmento')
            ->pluck('ser_id');
        if ($series->isEmpty()) {
            return;
        }

        DB::table('edu_escola_segmento')->insert([
            'esc_id'        => $escId,
            'seg_id'        => $segId,
            'anl_id_inicio' => $anlId,
            'anl_id_fim'    => null,
            'ser_id_inicio' => (int) $series->first(),
            'ser_id_fim'    => (int) $series->last(),
            'esg_fl_ativo'  => true,
            'esg_motivo'    => 'Vínculo criado automaticamente pela importação do Censo.',
            'esg_created_at' => $now,
            'esg_updated_at' => $now,
        ]);
    }

    // ---------- Pessoas (reg 30) ----------

    private function commitPessoas(CensoLote $lote, array &$resumo): void
    {
        CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)
            ->where('icr_tipo', '30')
            ->chunkById(1000, function ($registros) use (&$resumo) {
                foreach ($registros as $reg) {
                    $campos = $reg->icr_campos;
                    $inep = $this->col($campos, 4);

                    if ($inep === '') {
                        $this->marcarErro($reg, 'INEP da pessoa ausente.');
                        $resumo['pessoas_erro']++;
                        continue;
                    }

                    $ehAluno = isset($this->alunoInepSet[$inep]);
                    $ehFunc = isset($this->funcInepSet[$inep]);

                    if (! $ehAluno && ! $ehFunc) {
                        $resumo['pessoas_sem_vinculo']++;
                        continue;
                    }

                    try {
                        if ($ehAluno) {
                            $this->upsertAluno($reg, $campos, $inep, $resumo);
                        }
                        if ($ehFunc) {
                            $this->upsertFuncionario($campos, $inep, $resumo);
                        }
                    } catch (\Throwable $e) {
                        $this->marcarErro($reg, 'Falha na pessoa: '.$e->getMessage());
                        $resumo['pessoas_erro']++;
                    }
                }
            });
    }

    private function upsertAluno(CensoRegistro $reg, array $campos, string $inep, array &$resumo): void
    {
        if (isset($this->alunoExistente[$inep])) {
            $resumo['alunos_ignorados']++;

            return;
        }

        $munId = $this->resolverMunicipioNasc($campos);
        if ($munId === null) {
            $this->marcarErro($reg, 'Município de naturalidade não resolvido (aluno).');
            $resumo['pessoas_erro']++;

            return;
        }

        $cpf = $this->cpf11($this->col($campos, 5));
        if ($cpf !== null && isset($this->cpfAlunoUsado[$cpf])) {
            $cpf = null; // evita colisão de unique — mantém aluno por INEP
        }
        [$cidade, $uf] = $this->municipioNomeUf($this->col($campos, 53));
        $now = now();

        $alnId = DB::table('edu_aluno')->insertGetId([
            'aln_nome'          => mb_substr($this->col($campos, 6) ?: 'SEM NOME', 0, 100),
            'aln_dt_nascimento' => $this->parseData($this->col($campos, 7)) ?? '1900-01-01',
            'aln_sexo'          => $this->sexo($this->col($campos, 11)),
            'aln_cor_raca'      => (int) ($this->col($campos, 12) ?: 0),
            'aln_pais_origem'   => 'Brasil',
            'aln_mun_id_nasc'   => $munId,
            'aln_cpf'           => $cpf,
            'aln_cd_inep'       => $inep,
            'aln_filiacao_1'      => mb_substr($this->col($campos, 9), 0, 100) ?: null,
            'aln_filiacao_1_tipo' => $this->col($campos, 9) !== '' ? 'MAE' : null,
            'aln_filiacao_2'      => mb_substr($this->col($campos, 10), 0, 100) ?: null,
            'aln_filiacao_2_tipo' => $this->col($campos, 10) !== '' ? 'PAI' : null,
            'aln_cep'           => $this->cepOuNull($this->col($campos, 52)),
            'aln_cidade'        => $cidade,
            'aln_uf'            => $uf,
            'aln_email'         => mb_substr($this->col($campos, 108), 0, 150) ?: null,
            'aln_fl_ativo'      => true,
            'aln_created_at'    => $now,
            'aln_updated_at'    => $now,
        ], 'aln_id');

        $this->alunoExistente[$inep] = true;
        if ($cpf !== null) {
            $this->cpfAlunoUsado[$cpf] = true;
        }
        $resumo['alunos_criados']++;

        $this->upsertSaude((int) $alnId, $campos, $resumo);
    }

    private function upsertSaude(int $alnId, array $campos, array &$resumo): void
    {
        $temDef = $this->col($campos, 17) === '1';
        $altas = $this->col($campos, 28) === '1';

        // Deficiências (cols 18..27) e transtornos de aprendizagem (cols 30..35).
        $deficiencias = $this->flagsSetadas($campos, [
            18 => 'cegueira', 19 => 'baixa_visao', 20 => 'visao_monocular', 21 => 'surdez',
            22 => 'deficiencia_auditiva', 23 => 'surdocegueira', 24 => 'fisica',
            25 => 'intelectual', 26 => 'multipla', 27 => 'autismo',
        ]);
        $transtornos = $this->flagsSetadas($campos, [
            30 => 'discalculia', 31 => 'disgrafia', 32 => 'dislalia',
            33 => 'dislexia', 34 => 'tdah', 35 => 'tpac',
        ]);
        $recursos = $this->flagsSetadas($campos, [
            36 => 'ledor', 37 => 'transcricao', 38 => 'guia_interprete', 39 => 'tradutor_libras',
            40 => 'leitura_labial', 41 => 'prova_ampliada', 42 => 'prova_superampliada',
            43 => 'audio', 44 => 'lingua_port_2a', 45 => 'video_libras', 46 => 'braile',
            47 => 'prova_braile', 48 => 'tempo_adicional',
        ]);

        if (! $temDef && ! $altas && ! $deficiencias && ! $transtornos) {
            return; // nada de saúde relevante
        }

        DB::table('edu_aluno_saude')->insert([
            'als_aln_id'                   => $alnId,
            'als_fl_pcd'                   => $temDef,
            'als_fl_altas_habilidades'     => $altas,
            'als_deficiencias'             => $deficiencias ? json_encode($deficiencias, JSON_UNESCAPED_UNICODE) : null,
            'als_transtornos_aprendizagem' => $transtornos ? json_encode($transtornos, JSON_UNESCAPED_UNICODE) : null,
            'als_recursos_inep'            => $recursos ? json_encode($recursos, JSON_UNESCAPED_UNICODE) : null,
            'als_created_at'               => now(),
            'als_updated_at'               => now(),
        ]);

        $resumo['saude_criada']++;
    }

    private function upsertFuncionario(array $campos, string $inep, array &$resumo): void
    {
        if (isset($this->funcExistente[$inep])) {
            $resumo['funcionarios_ignorados']++;

            return;
        }

        $munId = $this->resolverMunicipioNasc($campos);
        if ($munId === null) {
            $resumo['pessoas_erro']++;

            return;
        }

        $cpf = $this->cpf11($this->col($campos, 5));
        if ($cpf !== null && isset($this->cpfFuncUsado[$cpf])) {
            $cpf = null;
        }
        $escol = $this->col($campos, 56);
        $now = now();

        DB::table('edu_funcionario')->insert([
            'fun_nome'          => mb_substr($this->col($campos, 6) ?: 'SEM NOME', 0, 100),
            'fun_dt_nascimento' => $this->parseData($this->col($campos, 7)) ?? '1900-01-01',
            'fun_sexo'          => $this->sexo($this->col($campos, 11)),
            'fun_cor_raca'      => (int) ($this->col($campos, 12) ?: 0),
            'fun_nacionalidade' => 'Brasileira',
            'fun_pais_origem'   => 'Brasil',
            'fun_mun_id_nasc'   => $munId,
            'fun_cpf'           => $cpf,
            'fun_escolaridade'  => $escol !== '' ? (int) $escol : null,
            'fun_cd_censo'      => $inep,
            'fun_cep'           => $this->cepOuNull($this->col($campos, 52)),
            'fun_email'         => mb_substr($this->col($campos, 108), 0, 150) ?: null,
            'fun_fl_ativo'      => true,
            'fun_created_at'    => $now,
            'fun_updated_at'    => $now,
        ]);

        $this->funcExistente[$inep] = true;
        if ($cpf !== null) {
            $this->cpfFuncUsado[$cpf] = true;
        }
        $resumo['funcionarios_criados']++;
    }

    // ---------- Matrículas (reg 60) ----------

    private function commitMatriculas(CensoLote $lote, array &$resumo): void
    {
        // Caches: aluno INEP → aln_id; turma INEP → {tur_id, ser_id}; matrículas ativas existentes.
        $alunoIdPorInep = DB::table('edu_aluno')->whereNotNull('aln_cd_inep')
            ->pluck('aln_id', 'aln_cd_inep')->all();

        $turmaPorInep = DB::table('edu_turma')->whereNotNull('tur_cd_inep')->whereNull('tur_deleted_at')
            ->get(['tur_id', 'tur_ser_id', 'tur_cd_inep'])->keyBy('tur_cd_inep');

        $matriculaAtiva = DB::table('edu_turma_aluno')
            ->where('tma_anl_id', $lote->icl_anl_id)
            ->where('tma_situacao', 'ATIVA')
            ->whereNull('tma_deleted_at')
            ->pluck('tma_aln_id')->flip()->map(fn () => true)->all();

        $dtMatricula = now()->toDateString();

        CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)
            ->where('icr_tipo', '60')
            ->chunkById(2000, function ($registros) use (&$resumo, $alunoIdPorInep, $turmaPorInep, &$matriculaAtiva, $lote, $dtMatricula) {
                foreach ($registros as $reg) {
                    $campos = $reg->icr_campos;
                    $inepAluno = $this->col($campos, 4);
                    $inepTurma = $this->col($campos, 6);

                    $alnId = $alunoIdPorInep[$inepAluno] ?? null;
                    if (! $alnId) {
                        $resumo['matriculas_sem_aluno']++;
                        continue;
                    }

                    $turma = $turmaPorInep[$inepTurma] ?? null;
                    if (! $turma) {
                        $resumo['matriculas_sem_turma']++;
                        continue;
                    }

                    if (isset($matriculaAtiva[$alnId])) {
                        $resumo['matriculas_ignoradas']++; // já tem matrícula ativa no ano
                        continue;
                    }

                    $now = now();
                    DB::table('edu_turma_aluno')->insert([
                        'tma_aln_id'      => $alnId,
                        'tma_tur_id'      => $turma->tur_id,
                        'tma_ser_id'      => $turma->tur_ser_id,
                        'tma_anl_id'      => $lote->icl_anl_id,
                        'tma_situacao'    => 'ATIVA',
                        'tma_dt_matricula' => $dtMatricula,
                        'tma_created_at'  => $now,
                        'tma_updated_at'  => $now,
                    ]);

                    $matriculaAtiva[$alnId] = true;
                    $resumo['matriculas_criadas']++;
                }
            });
    }

    // ---------- Lotações (reg 40 gestor / reg 50 docente) ----------

    /** @var array<string,int> cargo de-para: "origem|codigo" => crg_id resolvido */
    private array $cargoCache = [];

    private function commitLotacoes(CensoLote $lote, array &$resumo): void
    {
        $funcId = DB::table('edu_funcionario')->whereNotNull('fun_cd_censo')
            ->pluck('fun_id', 'fun_cd_censo')->all();
        $escId = DB::table('edu_escola')->pluck('esc_id', 'esc_cd_inep')->all();

        // De-para de cargo já com crg_id resolvido (quando houver).
        $this->cargoCache = [];
        foreach (DB::table('cfg_censo_funcao_depara')->whereNotNull('cfd_crg_id')->get() as $d) {
            $this->cargoCache[$d->cfd_origem.'|'.$d->cfd_codigo] = (int) $d->cfd_crg_id;
        }

        // Cargos docentes → marcam função em sala de aula "Docente" na lotação.
        $docenteIds = array_flip(\App\Support\CargoDocente::ids());

        $admPorFun = DB::table('edu_funcionario_admissao')
            ->pluck('adm_id', 'adm_fun_id')->map(fn ($v) => (int) $v)->all();
        $lotExistente = [];
        foreach (DB::table('edu_funcionario_lotacao')->get(['lot_adm_id', 'lot_esc_id']) as $r) {
            $lotExistente[$r->lot_adm_id.'|'.$r->lot_esc_id] = true;
        }

        $dtInicio = DB::table('cfg_ano_letivo')->where('anl_id', $lote->icl_anl_id)
            ->value('anl_dt_inicio_ano') ?: now()->toDateString();

        foreach ([['40', 'gestor', 5], ['50', 'docente', 7]] as [$tipo, $origem, $posCargo]) {
            CensoRegistro::query()
                ->where('icr_icl_id', $lote->icl_id)->where('icr_tipo', $tipo)
                ->chunkById(2000, function ($registros) use (&$resumo, $funcId, $escId, &$admPorFun, &$lotExistente, $dtInicio, $origem, $posCargo, $docenteIds) {
                    foreach ($registros as $reg) {
                        $campos = $reg->icr_campos;
                        $fid = $funcId[$this->col($campos, 4)] ?? null;
                        $eid = $escId[$this->col($campos, 2)] ?? null;

                        if (! $fid || ! $eid) {
                            $resumo['lotacoes_sem_dep']++;
                            continue;
                        }

                        try {
                            $crg = $this->resolverCargo($origem, (int) ($this->col($campos, $posCargo) ?: 0));
                            if ($crg === null) {
                                $resumo['lotacoes_sem_cargo']++;
                                continue; // função sem de-para → não inventa cargo
                            }

                            $adm = $admPorFun[$fid] ?? null;
                            if ($adm === null) {
                                $adm = (int) DB::table('edu_funcionario_admissao')->insertGetId([
                                    'adm_fun_id'    => $fid,
                                    'adm_matricula' => 'CENSO'.$this->col($campos, 4),
                                    'adm_dt_admissao' => $dtInicio,
                                    'adm_crg_id'    => $crg,
                                    'adm_created_at' => now(),
                                    'adm_updated_at' => now(),
                                ], 'adm_id');
                                $admPorFun[$fid] = $adm;
                                $resumo['admissoes_criadas']++;
                            }

                            $k = $adm.'|'.$eid;
                            if (isset($lotExistente[$k])) {
                                $resumo['lotacoes_ignoradas']++;
                                continue;
                            }

                            DB::table('edu_funcionario_lotacao')->insert([
                                'lot_adm_id'   => $adm,
                                'lot_esc_id'   => $eid,
                                'lot_crg_id'   => $crg,
                                'lot_vinculo'  => 'CENSO',
                                'lot_dt_inicio' => $dtInicio,
                                'lot_fl_ativo' => true,
                                'lot_funcoes_sala_aula' => isset($docenteIds[$crg])
                                    ? json_encode(['Docente'], JSON_UNESCAPED_UNICODE) : null,
                                'lot_created_at' => now(),
                                'lot_updated_at' => now(),
                            ]);
                            $lotExistente[$k] = true;
                            $resumo['lotacoes_criadas']++;
                        } catch (\Throwable $e) {
                            $this->marcarErro($reg, 'Falha na lotação: '.$e->getMessage());
                            $resumo['lotacoes_sem_dep']++;
                        }
                    }
                });
        }
    }

    /**
     * De-para função→cargo: retorna o crg_id mapeado em cfg_censo_funcao_depara,
     * ou null se a função não estiver mapeada. NÃO cria cargo (o cargo é cadastro
     * do cliente — ex.: "Docente I/II/III"). Função sem de-para bloqueia a lotação.
     */
    private function resolverCargo(string $origem, int $codigo): ?int
    {
        return $this->cargoCache[$origem.'|'.$codigo] ?? null;
    }

    // ---------- Turma-professor (reg 50) ----------

    private function commitTurmaProfessor(CensoLote $lote, array &$resumo): void
    {
        $funcId = DB::table('edu_funcionario')->whereNotNull('fun_cd_censo')
            ->pluck('fun_id', 'fun_cd_censo')->all();
        $turmaId = DB::table('edu_turma')->whereNotNull('tur_cd_inep')->whereNull('tur_deleted_at')
            ->pluck('tur_id', 'tur_cd_inep')->all();
        $disPorComp = DB::table('cfg_censo_disciplina_depara')->whereNotNull('cdd_dis_id')
            ->pluck('cdd_dis_id', 'cdd_codigo')->map(fn ($v) => (int) $v)->all();
        $tupExistente = [];
        foreach (DB::table('edu_turma_professor')->whereNull('tup_deleted_at')
            ->get(['tup_tur_id', 'tup_fun_id', 'tup_dis_id']) as $r) {
            $tupExistente[$r->tup_tur_id.'|'.$r->tup_fun_id.'|'.$r->tup_dis_id] = true;
        }

        CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)->where('icr_tipo', '50')
            ->chunkById(2000, function ($registros) use (&$resumo, $funcId, $turmaId, $disPorComp, &$tupExistente) {
                foreach ($registros as $reg) {
                    $campos = $reg->icr_campos;
                    $fid = $funcId[$this->col($campos, 4)] ?? null;
                    $tid = $turmaId[$this->col($campos, 6)] ?? null;

                    if (! $fid) {
                        continue;
                    }
                    if (! $tid) {
                        $resumo['turma_prof_sem_turma']++;
                        continue;
                    }

                    // Componentes que leciona: cols 9..33 (valores = códigos de componente).
                    for ($pos = 9; $pos <= 33; $pos++) {
                        $cod = (int) $this->col($campos, $pos);
                        if ($cod === 0) {
                            continue;
                        }
                        $dis = $disPorComp[$cod] ?? null;
                        if (! $dis) {
                            $resumo['turma_prof_bloqueados']++;
                            continue;
                        }
                        $k = $tid.'|'.$fid.'|'.$dis;
                        if (isset($tupExistente[$k])) {
                            continue;
                        }
                        DB::table('edu_turma_professor')->insert([
                            'tup_tur_id'     => $tid,
                            'tup_fun_id'     => $fid,
                            'tup_dis_id'     => $dis,
                            'tup_created_at' => now(),
                            'tup_updated_at' => now(),
                        ]);
                        $tupExistente[$k] = true;
                        $resumo['turma_prof_criados']++;
                    }
                }
            });
    }

    // ---------- Helpers ----------

    /** Naturalidade: nascimento (col16) → residência (col53) → município da escola (col2). */
    private function resolverMunicipioNasc(array $campos): ?int
    {
        foreach ([16, 53] as $pos) {
            $ibge = $this->col($campos, $pos);
            if ($ibge !== '' && isset($this->municipioCache[$ibge])) {
                return $this->municipioCache[$ibge];
            }
        }

        return $this->escolaMunCache[$this->col($campos, 2)] ?? null;
    }

    /** Nome/UF do município de residência (IBGE) — para aln_cidade/aln_uf. */
    private function municipioNomeUf(string $ibge): array
    {
        if ($ibge === '' || ! isset($this->municipioCache[$ibge])) {
            return [null, null];
        }
        $m = DB::table('edu_municipio')->where('mun_id', $this->municipioCache[$ibge])->first(['mun_nome', 'mun_uf']);

        return $m ? [$m->mun_nome, $m->mun_uf] : [null, null];
    }

    private function parseData(string $raw): ?string
    {
        if (! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    /** Censo sexo: 1=Masculino, 2=Feminino. */
    private function sexo(string $raw): string
    {
        return $raw === '2' ? 'F' : 'M';
    }

    private function cpf11(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw);

        return strlen($d) === 11 ? $d : null;
    }

    private function cepOuNull(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw);

        return strlen($d) === 8 ? $d : null;
    }

    /**
     * @param  array<int,string>  $mapa  posição => rótulo
     * @return array<int,string>  rótulos cujas flags == '1'
     */
    private function flagsSetadas(array $campos, array $mapa): array
    {
        $out = [];
        foreach ($mapa as $pos => $label) {
            if ($this->col($campos, $pos) === '1') {
                $out[] = $label;
            }
        }

        return $out;
    }

    /** Coluna 1-based do array bruto. */
    private function col(array $campos, int $pos): string
    {
        return trim((string) ($campos[$pos - 1] ?? ''));
    }

    private function resolverMunicipio(string $ibge): ?int
    {
        return $this->municipioCache[$ibge] ?? null;
    }

    private function findOrCreateBairro(int $munId, string $nome): int
    {
        $nome = mb_substr(trim($nome) ?: 'NÃO INFORMADO', 0, 100);
        $key = $munId.'|'.mb_strtoupper($nome);

        if (isset($this->bairroCache[$key])) {
            return $this->bairroCache[$key];
        }

        $id = DB::table('edu_bairro')
            ->where('bai_mun_id', $munId)
            ->where('bai_nome', $nome)
            ->value('bai_id');

        if (! $id) {
            $now = now();
            $id = DB::table('edu_bairro')->insertGetId([
                'bai_mun_id'     => $munId,
                'bai_nome'       => $nome,
                'bai_fl_ativo'   => true,
                'bai_created_at' => $now,
                'bai_updated_at' => $now,
            ], 'bai_id');
        }

        return $this->bairroCache[$key] = (int) $id;
    }

    private function cep(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);

        return str_pad(mb_substr($d, 0, 8), 8, '0', STR_PAD_LEFT);
    }

    /** Censo zona: 1=Urbana, 2=Rural. */
    private function zona(string $raw): string
    {
        return $raw === '2' ? 'R' : 'U';
    }

    /** Deriva turno pelo horário "HH:MM-HH:MM" (Turma::TURNOS). */
    private function turno(string $horario): string
    {
        $ini = $this->hora($horario, 0);
        if ($ini === null) {
            return 'INTEGRAL';
        }
        $h = (int) explode(':', $ini)[0];

        return match (true) {
            $h < 12 => 'MATUTINO',
            $h < 18 => 'VESPERTINO',
            default => 'NOTURNO',
        };
    }

    /** Extrai HH:MM:00 do horário "HH:MM-HH:MM"; $qual 0=início, 1=fim. */
    private function hora(string $horario, int $qual): ?string
    {
        if ($horario === '' || ! str_contains($horario, '-')) {
            return null;
        }
        $partes = explode('-', $horario);
        $hm = trim($partes[$qual] ?? '');
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hm)) {
            return null;
        }

        return $hm.':00';
    }

    /**
     * Dias de funcionamento. No reg20 as cols 7..13 (dom..sáb) trazem o HORÁRIO
     * do dia (ex. "13:30-17:30") quando há aula, ou vazio quando não há — não é flag.
     * Dia ativo = coluna não-vazia. Valores casam com Turma::DIAS_SEMANA (3 letras).
     */
    private function dias(array $campos): array
    {
        $labels = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
        $dias = [];
        foreach ($labels as $i => $label) {
            if ($this->col($campos, 7 + $i) !== '') {
                $dias[] = $label;
            }
        }

        return $dias;
    }

    private function marcarErro(CensoRegistro $reg, string $msg): void
    {
        $reg->update(['icr_status' => 'ERRO', 'icr_erro' => $msg]);
    }
}
