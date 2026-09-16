<?php

namespace App\Services\Censo;

use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Turma\Turma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exportação do arquivo de Matrícula Inicial do Educacenso 2026, POR ESCOLA.
 *
 * Formato (docs/censo/regras-gerais.md): registros separados por linha, campos
 * separados por pipe "|", codificação ISO-8859-1 (feita no controller ao baixar),
 * texto em MAIÚSCULAS sem acento, arquivo termina com a linha "99|".
 *
 * Cada registro é montado como array 1-based de 53/187/... campos (posição do layout
 * 2026, ver docs/censo/NN.md). Traduções de valor (domínios) seguem a planilha.
 *
 * ESCOPO: rede pública municipal (dependência = 3). Blocos de mantenedora/privada e
 * parcerias (reg 00, 26-48) saem em branco. Implementado até agora: Reg 00. Demais
 * registros serão adicionados na sequência.
 */
class CensoExportService
{
    /** Monta o conteúdo completo do arquivo (UTF-8; controller converte p/ ISO-8859-1). */
    public function gerar(Escola $escola, AnoLetivo $ano): string
    {
        $escola->loadMissing(['municipio:mun_id,mun_codigo_ibge,mun_uf', 'bairro:bai_id,bai_nome']);

        $inep = $this->num($escola->esc_cd_inep, 8);

        $linhas = [];
        $linhas[] = $this->linha($this->reg00($escola, $ano));
        $linhas[] = $this->linha($this->reg10($escola, $inep));
        foreach ($this->turmasDaEscola($escola, $ano) as $t) {
            $linhas[] = $this->linha($this->reg20($t, $inep));
        }
        // Reg 30 — pessoa física (alunos matriculados + profissionais lotados).
        foreach ($this->pessoas($escola, $ano) as $p) {
            $linhas[] = $this->linha($this->reg30($p, $inep));
        }
        // Reg 40 — vínculo do gestor escolar.
        foreach ($this->gestores($escola) as $g) {
            $linhas[] = $this->linha($this->reg40($g, $inep, $escola));
        }
        // Reg 50 — vínculo do docente com a turma.
        foreach ($this->docentes($escola, $ano) as $d) {
            $linhas[] = $this->linha($this->reg50($d, $inep));
        }
        // Reg 60 — matrícula do aluno.
        foreach ($this->matriculas($escola, $ano) as $m) {
            $linhas[] = $this->linha($this->reg60($m, $inep));
        }
        $linhas[] = '99|';

        return implode("\r\n", $linhas) . "\r\n";
    }

    /** @return \Illuminate\Support\Collection<int,Turma> */
    private function turmasDaEscola(Escola $e, AnoLetivo $ano)
    {
        return Turma::query()
            ->where('tur_esc_id', $e->esc_id)
            ->where('tur_anl_id', $ano->anl_id)
            ->orderBy('tur_nome')
            ->get(['tur_id', 'tur_nome', 'tur_mediacao', 'tur_dias_funcionamento',
                'tur_hora_inicio', 'tur_hora_fim', 'tur_local_diferenciado',
                'tur_modalidade', 'tur_ser_id']);
    }

    // ------------------------------------------------------------- Reg 00

    /** @return array<int,string> 53 campos (1-based). */
    private function reg00(Escola $e, AnoLetivo $ano): array
    {
        $ibge = $e->municipio?->mun_codigo_ibge ?? '';
        $emAtividade = (int) $e->esc_situacao_func === 1;
        // Dependência pública (1-Federal, 2-Estadual, 3-Municipal) → órgão público vinculado
        // derivável: Secretaria de Educação = Sim; demais órgãos = Não (decisão de projeto).
        $publica = in_array((int) $e->esc_dep_administrativa, [1, 2, 3], true);

        $c = array_fill(1, 53, ''); // inicializa 1..53 vazios

        $c[1]  = '00';
        $c[2]  = $this->num($e->esc_cd_inep, 8);
        $c[3]  = (string) ((int) $e->esc_situacao_func ?: '');
        $c[4]  = $emAtividade ? $this->data($ano->anl_dt_inicio_ano) : '';
        $c[5]  = $emAtividade ? $this->data($ano->anl_dt_fim) : '';
        $c[6]  = $this->texto($e->esc_nome, 100);
        $c[7]  = $this->num($e->esc_cep, 8);
        $c[8]  = $this->num($ibge, 7);
        $c[9]  = mb_substr($ibge, 0, 2);          // Distrito = código IBGE da UF (decisão)
        $c[10] = $this->texto($e->esc_logradouro, 100);
        $c[11] = $this->texto($e->esc_numero, 100);
        $c[12] = $this->texto($e->esc_complemento, 20);
        $c[13] = $this->texto($e->bairro?->bai_nome, 50);
        $c[14] = $this->num($e->esc_ddd, 2);
        $c[15] = $this->num($e->esc_telefone_fixo, 9);
        $c[16] = '';                              // outro telefone — sem coluna
        $c[17] = $this->texto($e->esc_email, 100);
        $c[18] = $this->texto($e->esc_orgao_regional_ensino, 5);
        $c[19] = $e->esc_zona === 'R' ? '2' : '1'; // 1-Urbana; 2-Rural
        $c[20] = (string) ((int) ($e->esc_localizacao_dif ?: 7)); // 7 = não diferenciada
        $c[21] = (string) ((int) $e->esc_dep_administrativa ?: '');

        // 22-25 órgão público vinculado (obrig. p/ pública): Secretaria de Educação = Sim.
        if ($publica) {
            $c[22] = '1';
            $c[23] = '0';
            $c[24] = '0';
            $c[25] = '0';
        }

        // 26-48 mantenedora/categoria privada + parcerias/convênios → fora de escopo
        // (rede pública municipal): permanecem em branco.

        // 49 Regulamentação (0-Não; 1-Sim; 2-Em tramitação) quando em atividade.
        if ($emAtividade && $e->esc_regulamentada_conselho !== null) {
            $c[49] = (string) (int) $e->esc_regulamentada_conselho;
        }
        // 50-53 esfera do conselho / vínculo / escola sede / IES → sem dado (em branco).

        return $c;
    }

    // ------------------------------------------------------------- Reg 10 (infraestrutura)

    /** posição (10.md/2026) → coluna booleana de cfg_censo_escolar. */
    private const REG10_BOOL = [
        17 => 'cen_fl_agua_potavel',
        18 => 'cen_agu_rede_publica', 19 => 'cen_agu_poco_artesiano', 20 => 'cen_agu_cacimba',
        21 => 'cen_agu_fonte_rio', 22 => 'cen_agu_carro_pipa', 23 => 'cen_agu_inexistente',
        24 => 'cen_ene_rede_publica', 25 => 'cen_ene_gerador_fossil', 26 => 'cen_ene_renovavel', 27 => 'cen_ene_inexistente',
        28 => 'cen_esg_rede_publica', 29 => 'cen_esg_fossa_septica', 30 => 'cen_esg_fossa_rudimentar', 31 => 'cen_esg_inexistente',
        32 => 'cen_lxd_coleta', 33 => 'cen_lxd_queima', 34 => 'cen_lxd_enterra', 35 => 'cen_lxd_destinacao_licenciada', 36 => 'cen_lxd_outra_area',
        37 => 'cen_lxt_separacao', 38 => 'cen_lxt_reaproveitamento', 39 => 'cen_lxt_reciclagem', 40 => 'cen_lxt_nao_faz',
        41 => 'cen_dep_almoxarifado', 42 => 'cen_dep_vegetacao', 43 => 'cen_dep_auditorio',
        45 => 'cen_dep_ban_pcd', 46 => 'cen_dep_ban_infantil', 47 => 'cen_dep_ban_funcionarios', 48 => 'cen_dep_ban_chuveiro',
        49 => 'cen_dep_biblioteca', 50 => 'cen_dep_cozinha', 51 => 'cen_dep_despensa', 52 => 'cen_dep_dorm_aluno', 53 => 'cen_dep_dorm_professor',
        54 => 'cen_dep_lab_ciencias', 55 => 'cen_dep_lab_informatica', 56 => 'cen_dep_lab_robotica', 57 => 'cen_dep_lab_profissional',
        58 => 'cen_dep_parque_infantil', 59 => 'cen_dep_patio_coberto', 60 => 'cen_dep_patio_descoberto', 61 => 'cen_dep_piscina',
        62 => 'cen_dep_quadra_coberta', 63 => 'cen_dep_quadra_descoberta', 64 => 'cen_dep_refeitorio', 65 => 'cen_dep_repouso_aluno',
        66 => 'cen_dep_sala_artes', 67 => 'cen_dep_sala_musica', 68 => 'cen_dep_sala_danca', 69 => 'cen_dep_sala_multiuso',
        70 => 'cen_dep_terreirao', 71 => 'cen_dep_viveiro', 72 => 'cen_dep_sala_diretoria', 73 => 'cen_dep_sala_leitura',
        74 => 'cen_dep_sala_professores', 75 => 'cen_dep_sala_aee', 76 => 'cen_dep_sala_secretaria', 77 => 'cen_dep_sala_oficinas_prof',
        78 => 'cen_dep_estudio_gravacao', 79 => 'cen_dep_horta', 80 => 'cen_dep_nenhuma',
        81 => 'cen_ace_corrimao', 82 => 'cen_ace_elevador', 83 => 'cen_ace_pisos_tateis', 84 => 'cen_ace_portas_80cm', 85 => 'cen_ace_rampas',
        86 => 'cen_ace_alarme_luminoso', 87 => 'cen_ace_sinalizacao_sonora', 88 => 'cen_ace_sinalizacao_tatil', 89 => 'cen_ace_sinalizacao_visual', 90 => 'cen_ace_nenhuma',
        96 => 'cen_eqp_antena', 97 => 'cen_eqp_computadores', 98 => 'cen_eqp_copiadora', 99 => 'cen_eqp_impressora', 100 => 'cen_eqp_multifuncional', 101 => 'cen_eqp_scanner', 102 => 'cen_eqp_nenhum',
        111 => 'cen_net_admin', 112 => 'cen_net_ensino', 113 => 'cen_net_alunos', 114 => 'cen_net_comunidade', 115 => 'cen_net_nenhum',
        140 => 'cen_mat_acervo_multimidia', 141 => 'cen_mat_brinquedos_inf', 142 => 'cen_mat_cientifico', 143 => 'cen_mat_amplificacao_som',
        144 => 'cen_mat_audiovisual_prod', 145 => 'cen_mat_horta_equip', 146 => 'cen_mat_instrumentos_musicais', 147 => 'cen_mat_jogos_educativos',
        148 => 'cen_mat_robotica', 149 => 'cen_mat_atividades_culturais', 150 => 'cen_mat_educ_emocional', 151 => 'cen_mat_educ_profissional',
        152 => 'cen_mat_esporte_recreacao', 153 => 'cen_mat_educ_surdos', 154 => 'cen_mat_educ_indigena', 155 => 'cen_mat_etnico_racial',
        156 => 'cen_mat_educ_campo', 157 => 'cen_mat_educ_quilombola', 158 => 'cen_mat_educ_especial', 159 => 'cen_mat_nenhum',
        171 => 'cen_fl_site', 172 => 'cen_fl_compartilha_espacos', 173 => 'cen_fl_usa_entorno',
        174 => 'cen_org_assoc_pais', 175 => 'cen_org_assoc_pais_mestres', 176 => 'cen_org_conselho_escolar', 177 => 'cen_org_gremio', 178 => 'cen_org_outros', 179 => 'cen_org_nenhum',
        181 => 'cen_fl_educ_ambiental',
        182 => 'cen_amb_conteudo_curriculo', 183 => 'cen_amb_comp_curricular', 184 => 'cen_amb_eixo_estruturante', 185 => 'cen_amb_eventos', 186 => 'cen_amb_projetos_transversais', 187 => 'cen_amb_nenhuma',
    ];

    /** posição (10.md/2026) → coluna inteira (quantidade). */
    private const REG10_INT = [
        91 => 'cen_sal_dentro_predio', 92 => 'cen_sal_fora_predio', 93 => 'cen_sal_climatizadas', 94 => 'cen_sal_pcd', 95 => 'cen_sal_cantinho_leitura',
        103 => 'cen_ens_dvd_qty', 104 => 'cen_ens_som_qty', 105 => 'cen_ens_tv_qty', 106 => 'cen_ens_lousa_digital_qty',
        107 => 'cen_ens_projetor_qty', 108 => 'cen_ens_desktop_alunos_qty', 109 => 'cen_ens_notebook_alunos_qty', 110 => 'cen_ens_tablet_alunos_qty',
        119 => 'cen_pro_agronomo_qty', 120 => 'cen_pro_assist_social_qty', 121 => 'cen_pro_aux_secretaria_qty', 122 => 'cen_pro_aux_servicos_qty',
        123 => 'cen_pro_bibliotecario_qty', 124 => 'cen_pro_bombeiro_qty', 125 => 'cen_pro_coord_turno_qty', 126 => 'cen_pro_fonoaudiologo_qty',
        128 => 'cen_pro_psicologo_qty', 129 => 'cen_pro_cozinha_qty', 130 => 'cen_pro_coord_pedagogico_qty', 131 => 'cen_pro_secretario_qty',
        132 => 'cen_pro_seguranca_qty', 133 => 'cen_pro_tec_laboratorio_qty', 134 => 'cen_pro_vice_diretor_qty', 135 => 'cen_pro_orientador_comun_qty',
        136 => 'cen_pro_interprete_libras_qty', 137 => 'cen_pro_revisor_braille_qty', 180 => 'cen_ppp',
    ];

    /** @return array<int,string> 187 campos. */
    private function reg10(Escola $e, string $inep): array
    {
        $c = array_fill(1, 187, '');
        $cen = $e->censos()->first();
        $b = fn (string $col) => $cen && $cen->$col ? '1' : '0';
        $i = function (string $col) use ($cen) {
            $v = $cen?->$col;

            return $v === null || $v === '' ? '' : (string) ((int) $v);
        };

        $c[1] = '10';
        $c[2] = $inep;

        // 3-8 local de funcionamento (edu_escola)
        $c[3] = $e->esc_fl_local_predio ? '1' : '0';
        $c[4] = $e->esc_fl_local_outra_escola ? '1' : '0';
        $c[5] = $e->esc_fl_local_galpao ? '1' : '0';
        $c[6] = $e->esc_fl_local_socioeducativa ? '1' : '0';
        $c[7] = $e->esc_fl_local_prisional ? '1' : '0';
        $c[8] = $e->esc_fl_local_outros ? '1' : '0';
        // 9 forma de ocupação do prédio (quando há prédio próprio)
        $c[9]  = $e->esc_fl_local_predio && $e->esc_forma_ocupacao ? (string) ((int) $e->esc_forma_ocupacao) : '';
        $c[10] = $e->esc_fl_predio_compartilhado ? '1' : '0';
        // 11-16 escolas compartilhadas → sem dado.

        // 17-187 via mapas (cfg_censo_escolar)
        foreach (self::REG10_BOOL as $pos => $col) {
            $c[$pos] = $b($col);
        }
        foreach (self::REG10_INT as $pos => $col) {
            $c[$pos] = $i($col);
        }

        // Derivados
        // 44 Banheiro (genérico) = 1 se houver qualquer banheiro específico.
        $c[44] = ($cen && ($cen->cen_dep_ban_pcd || $cen->cen_dep_ban_infantil || $cen->cen_dep_ban_funcionarios || $cen->cen_dep_ban_chuveiro)) ? '1' : '0';
        // 116 equipamentos que alunos usam (seleção 1/2/3) — a partir dos dispositivos.
        if ($cen) {
            $de = $cen->cen_net_disp_escola;
            $dp = $cen->cen_net_disp_pessoal;
            $c[116] = $de && $dp ? '3' : ($de ? '1' : ($dp ? '2' : ''));
            // 117 Internet banda larga = 1 se rede cabo/wifi.
            $c[117] = ($cen->cen_net_tipo_cabo || $cen->cen_net_tipo_wifi) ? '1' : '0';
        }
        // 118-120 rede local, 160-170 língua indígena/exame/cotas → sem coluna (em branco).

        return $c;
    }

    // ------------------------------------------------------------- Reg 20

    /** @var array<int,int>|null série → código de etapa Educacenso (reverse do de-para) */
    private ?array $etapaPorSerie = null;

    /** @return array<int,string> 66 campos (1-based). */
    private function reg20(Turma $t, string $inep): array
    {
        $c = array_fill(1, 66, '');

        $c[1]  = '20';
        $c[2]  = $inep;
        $c[3]  = mb_substr((string) $t->tur_id, 0, 8);   // código da turma no sistema
        $c[4]  = '';                                     // Inep da turma — vazio no layout
        $c[5]  = $this->texto($t->tur_nome, 80);
        $c[6]  = $this->mediacao($t->tur_mediacao);

        // 7-13 dias da semana (dom..sáb) com o horário quando há aula naquele dia.
        $horario = $this->horario($t->tur_hora_inicio, $t->tur_hora_fim);
        if ($horario !== '') {
            $pos = ['dom' => 7, 'seg' => 8, 'ter' => 9, 'qua' => 10, 'qui' => 11, 'sex' => 12, 'sab' => 13];
            foreach ($this->dias($t->tur_dias_funcionamento) as $d) {
                if (isset($pos[$d])) {
                    $c[$pos[$d]] = $horario;
                }
            }
        }

        $c[14] = $this->tipoTurma($t->tur_modalidade);
        // 15-20 atividade complementar (só p/ tipo 4/9) → fora de escopo por ora.
        $c[21] = (string) ((int) ($t->tur_local_diferenciado ?? 0)); // 0/1/2/3
        $c[22] = $t->tur_fl_classe_especial ? '1' : '0'; // Educação Especial (classe especial)
        // 23 etapa agregada → derivada/opcional (em branco).
        $c[24] = (string) ($this->etapaDaSerie((int) $t->tur_ser_id) ?? '');
        // 25-28 eixo/curso/CH/organização → fora de escopo (Ed. Profissional).
        $c[29] = $t->tur_fl_alternancia ? '1' : '0'; // Formação por Alternância (obrigatório)
        // 30-66 Ensino Médio/Novo EM/áreas do conhecimento → fora de escopo.

        return $c;
    }

    /** Código de etapa Educacenso da série (reverse do de-para; nulo se não mapeado). */
    private function etapaDaSerie(int $serId): ?int
    {
        if ($this->etapaPorSerie === null) {
            $this->etapaPorSerie = DB::table('cfg_censo_etapa_depara')
                ->where('ced_fl_ignorar', false)
                ->whereNotNull('ced_ser_id')
                ->pluck('ced_codigo', 'ced_ser_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return $this->etapaPorSerie[$serId] ?? null;
    }

    /** Mediação: PRESENCIAL→1, SEMIPRESENCIAL→2, EAD→3 (default 1). */
    private function mediacao(?string $m): string
    {
        return match ($m) {
            'SEMIPRESENCIAL' => '2',
            'EDUCAÇÃO A DISTÂNCIA', 'EAD' => '3',
            default => '1',
        };
    }

    /** Tipo de turma (campo 14): REGULAR→6, AEE→5, ATIVIDADE→4. */
    private function tipoTurma(?string $modalidade): string
    {
        return match ($modalidade) {
            Turma::MODALIDADE_AEE => '5',
            Turma::MODALIDADE_ATIVIDADE => '4',
            default => '6',
        };
    }

    /** "HH:MM-HH:MM" a partir de hora início/fim (que vêm "HH:MM:SS"); vazio se faltar. */
    private function horario(?string $ini, ?string $fim): string
    {
        $h = fn (?string $v) => $v ? mb_substr($v, 0, 5) : '';
        [$i, $f] = [$h($ini), $h($fim)];

        return $i !== '' && $f !== '' ? "{$i}-{$f}" : '';
    }

    /** @return array<int,string> dias (3 letras) a partir do json tur_dias_funcionamento. */
    private function dias($json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $arr = json_decode((string) $json, true);

        return is_array($arr) ? $arr : [];
    }

    // ------------------------------------------------------------- Reg 30 (pessoa física)

    /** Deficiências (campo censo → rótulo no JSON de saúde). */
    private const DEFIC = [
        18 => 'cegueira', 19 => 'baixa_visao', 20 => 'visao_monocular', 21 => 'surdez',
        22 => 'deficiencia_auditiva', 23 => 'surdocegueira', 24 => 'fisica',
        25 => 'intelectual', 26 => 'multipla', 27 => 'autismo',
    ];

    /** Transtornos de aprendizagem (campo censo → rótulo). */
    private const TRANST = [
        30 => 'discalculia', 31 => 'disgrafia', 32 => 'dislalia',
        33 => 'dislexia', 34 => 'tdah', 35 => 'tpac',
    ];

    /** Recursos SAEB (campo censo → rótulo). */
    private const RECUR = [
        36 => 'ledor', 37 => 'transcricao', 38 => 'guia_interprete', 39 => 'tradutor_libras',
        40 => 'leitura_labial', 41 => 'prova_ampliada', 42 => 'prova_superampliada',
        43 => 'audio', 44 => 'lingua_port_2a', 45 => 'video_libras', 46 => 'braile',
        47 => 'prova_braile', 48 => 'tempo_adicional',
    ];

    /** Pessoas da escola: alunos matriculados + profissionais lotados (normalizados). */
    private function pessoas(Escola $e, AnoLetivo $ano): Collection
    {
        $alunos = Matricula::query()
            ->with([
                'aluno.saude',
                'aluno.municipioNascimento:mun_id,mun_codigo_ibge',
                'aluno.municipioResidencia:mun_id,mun_codigo_ibge',
                'aluno.povoIndigena:pin_id,pin_cd_censo',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q->where('tur_esc_id', $e->esc_id)->where('tur_anl_id', $ano->anl_id))
            ->get()
            ->map(fn (Matricula $m) => $m->aluno)
            ->filter()
            ->unique('aln_id')
            ->map(fn ($a) => $this->pessoaAluno($a));

        $funcs = \App\Models\Funcionario\Funcionario::query()
            ->with([
                'municipioNascimento:mun_id,mun_codigo_ibge',
                'municipioResidencia:mun_id,mun_codigo_ibge',
            ])
            ->where('fun_fl_ativo', true)
            ->whereHas('admissoes.lotacoes', fn ($q) => $q->where('lot_esc_id', $e->esc_id)->where('lot_fl_ativo', true))
            ->get()
            ->map(fn ($f) => $this->pessoaFuncionario($f));

        return $alunos->values()->concat($funcs->values());
    }

    /** @return array<string,mixed> */
    private function pessoaAluno($a): array
    {
        $s = $a->saude;

        return [
            'cod'       => (string) $a->aln_id,
            'inep'      => $a->aln_cd_inep,
            'cpf'       => $a->aln_cpf,
            'nome'      => $a->aln_nome,
            'nasc'      => $a->aln_dt_nascimento,
            'sexo'      => $a->aln_sexo,
            'cor'       => $a->aln_cor_raca,
            'pin'       => $a->povoIndigena?->pin_cd_censo,
            'pais'      => $a->aln_pais_origem,
            'mun_nasc'  => $a->municipioNascimento?->mun_codigo_ibge,
            'fil1'      => $a->aln_filiacao_1,
            'fil2'      => $a->aln_filiacao_2,
            'certidao'  => $a->aln_nr_certidao,
            'cep'       => $a->aln_cep,
            'mun_resid' => $a->municipioResidencia?->mun_codigo_ibge,
            'zona'      => $a->aln_zona_residencia,
            'saude'     => $s ? [
                'pcd'    => (bool) $s->als_fl_pcd,
                'altas'  => (bool) $s->als_fl_altas_habilidades,
                'def'    => $this->jsonArr($s->als_deficiencias),
                'transt' => $this->jsonArr($s->als_transtornos_aprendizagem),
                'rec'    => $this->jsonArr($s->als_recursos_inep),
            ] : null,
        ];
    }

    /** @return array<string,mixed> */
    private function pessoaFuncionario($f): array
    {
        return [
            'cod'       => (string) $f->fun_id,
            'inep'      => $f->fun_cd_censo,
            'cpf'       => $f->fun_cpf,
            'nome'      => $f->fun_nome,
            'nasc'      => $f->fun_dt_nascimento,
            'sexo'      => $f->fun_sexo,
            'cor'       => $f->fun_cor_raca,
            'pin'       => null,
            'pais'      => $f->fun_pais_origem,
            'mun_nasc'  => $f->municipioNascimento?->mun_codigo_ibge,
            'fil1'      => $f->fun_filiacao_1,
            'fil2'      => $f->fun_filiacao_2,
            'certidao'  => null,
            'cep'       => $f->fun_cep,
            'mun_resid' => $f->municipioResidencia?->mun_codigo_ibge,
            'zona'      => $f->fun_zona_residencia,
            'saude'     => null, // funcionário: sem colunas de deficiência (gap conhecido)
        ];
    }

    /** @param array<string,mixed> $p @return array<int,string> 110 campos. */
    private function reg30(array $p, string $inep): array
    {
        $c = array_fill(1, 110, '');
        $s = $p['saude'];

        $c[1]  = '30';
        $c[2]  = $inep;
        $c[3]  = mb_substr($p['cod'], 0, 20);
        $c[4]  = $this->num($p['inep'], 12);
        $c[5]  = $this->num($p['cpf'], 11);
        $c[6]  = $this->texto($p['nome'], 100);
        $c[7]  = $this->data($p['nasc']);
        $c[8]  = (blank($p['fil1']) && blank($p['fil2'])) ? '0' : '1';
        $c[9]  = $this->texto($p['fil1'], 100);
        $c[10] = $this->texto($p['fil2'], 100);
        $c[11] = $p['sexo'] === 'F' ? '2' : '1';
        $c[12] = (string) ((int) $p['cor']);
        $c[13] = $this->num($p['pin'], 3);
        $c[14] = $this->ehBrasil($p['pais']) ? '1' : '3';
        // 15 país de nacionalidade → só p/ estrangeira; de-para de países pendente (em branco).
        $c[16] = $this->num($p['mun_nasc'], 7);

        // 17-49 deficiência / transtorno / recursos (só quando há saúde relevante).
        $temDef = $s && ($s['pcd'] || $s['altas'] || $s['def'] || $s['transt']);
        $c[17] = $temDef ? '1' : '0';
        if ($temDef) {
            foreach (self::DEFIC as $pos => $lab) {
                $c[$pos] = in_array($lab, $s['def'], true) ? '1' : '0';
            }
            $c[28] = $s['altas'] ? '1' : '0';
            $c[29] = $s['transt'] ? '1' : '0';
            foreach (self::TRANST as $pos => $lab) {
                $c[$pos] = in_array($lab, $s['transt'], true) ? '1' : '0';
            }
            foreach (self::RECUR as $pos => $lab) {
                $c[$pos] = in_array($lab, $s['rec'], true) ? '1' : '0';
            }
            $c[49] = empty($s['rec']) ? '1' : '0'; // Nenhum recurso
        }

        $c[50] = $this->texto($p['certidao'], 32);

        // 51-55 residência. País de residência = 76 (Brasil) por decisão de projeto.
        $temResid = ! blank($p['cep']) || ! blank($p['mun_resid']) || ! blank($p['zona']);
        $c[51] = $temResid ? '76' : '';
        $c[52] = $this->num($p['cep'], 8);
        $c[53] = $this->num($p['mun_resid'], 7);
        $c[54] = blank($p['zona']) ? '' : (string) ((int) $p['zona']);
        // 55 localização diferenciada de residência → sem coluna (em branco).
        // 56-110 escolaridade / formação do profissional → sem mapeamento ainda (em branco).

        return $c;
    }

    private function ehBrasil(?string $pais): bool
    {
        $p = $this->texto($pais, 50);

        return $p === '' || $p === 'BRASIL';
    }

    /** @return array<int,string> */
    private function jsonArr($v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $a = json_decode((string) $v, true);

        return is_array($a) ? $a : [];
    }

    // ------------------------------------------------------------- Reg 40 (gestor)

    /** Lotações de gestão (cargo com "diretor"/"gestor") ativas na escola. */
    private function gestores(Escola $e): Collection
    {
        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'a.adm_fun_id')
            ->join('edu_cargo as cg', 'cg.crg_id', '=', 'l.lot_crg_id')
            ->where('l.lot_esc_id', $e->esc_id)
            ->where('l.lot_fl_ativo', true)
            ->where('f.fun_fl_ativo', true)
            ->where(fn ($q) => $q->where('cg.crg_nome', 'ilike', '%diretor%')->orWhere('cg.crg_nome', 'ilike', '%gestor%'))
            ->distinct()
            ->get(['f.fun_id', 'f.fun_cd_censo', 'cg.crg_nome', 'l.lot_criterio_acesso', 'l.lot_vinculo']);
    }

    /** @return array<int,string> 7 campos. */
    private function reg40($g, string $inep, Escola $e): array
    {
        $c = array_fill(1, 7, '');
        $diretorTitular = self::isDiretorTitular($g->crg_nome);

        $c[1] = '40';
        $c[2] = $inep;
        $c[3] = (string) $g->fun_id;
        $c[4] = $this->num($g->fun_cd_censo, 12);
        $c[5] = $diretorTitular ? '1' : '2';
        $c[6] = $this->criterioAcesso($g->lot_criterio_acesso);
        // Campo 7 só é preenchido quando exigido; senão deve ir nulo.
        $c[7] = self::exigeSituacaoFuncional($diretorTitular, $e) ? self::situacaoFuncional($g->lot_vinculo) : '';

        return $c;
    }

    /** Cargo (campo 5 do Reg 40): Diretor(a) titular = 1; demais cargos de gestão = 2. */
    public static function isDiretorTitular(?string $crgNome): bool
    {
        $nome = mb_strtolower($crgNome ?? '');

        return str_contains($nome, 'diretor') && ! str_contains($nome, 'vice') && ! str_contains($nome, 'adjunto');
    }

    /**
     * Campo 7 do Reg 40 é obrigatório (1-4) quando Cargo = 1, escola em atividade (situação = 1)
     * e dependência administrativa pública (1, 2 ou 3); em qualquer outro caso deve ser nulo.
     */
    public static function exigeSituacaoFuncional(bool $diretorTitular, Escola $e): bool
    {
        return $diretorTitular
            && (int) $e->esc_situacao_func === 1
            && in_array((int) $e->esc_dep_administrativa, [1, 2, 3], true);
    }

    /**
     * Situação funcional/regime de contratação (campo 7) a partir do Vínculo da lotação:
     * 1 = Concursado/efetivo/estável; 2 = Contrato temporário; 3 = Contrato terceirizado; 4 = Contrato CLT.
     * Vazio quando o vínculo não tem correspondência (ex.: Comissionado, Função Gratificada).
     */
    public static function situacaoFuncional(?string $vinculo): string
    {
        $v = mb_strtolower((string) $vinculo);

        return match (true) {
            str_contains($v, 'concurs') || str_contains($v, 'efetiv') => '1',
            str_contains($v, 'tempor')  => '2',
            str_contains($v, 'tercei')  => '3',
            str_contains($v, 'clt')     => '4',
            default => '',
        };
    }

    /** Critério de acesso (campo 6): texto → código do domínio (1-7); vazio se desconhecido. */
    private function criterioAcesso(?string $txt): string
    {
        $t = mb_strtolower((string) $txt);
        if ($t === '') {
            return '';
        }

        return match (true) {
            str_contains($t, 'concurso')  => '4',
            str_contains($t, 'elei') && str_contains($t, 'seletivo') => '6',
            str_contains($t, 'elei')      => '5',
            str_contains($t, 'seletivo')  => '3',
            str_contains($t, 'indica') || str_contains($t, 'escolha') => '2',
            str_contains($t, 'propriet') => '1',
            default => '7', // Outros
        };
    }

    // ------------------------------------------------------------- Reg 50 (docente)

    /** Vínculos docente↔turma (por professor+turma) das turmas da escola no ano. */
    private function docentes(Escola $e, AnoLetivo $ano): Collection
    {
        // Agrupa disciplinas por (funcionário, turma); áreas do conhecimento ficam
        // pendentes de de-para disciplina→área (cfg_censo_disciplina_depara).
        return DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'tp.tup_fun_id')
            ->where('t.tur_esc_id', $e->esc_id)
            ->where('t.tur_anl_id', $ano->anl_id)
            ->whereNull('tp.tup_deleted_at')
            ->distinct()
            ->get(['tp.tup_fun_id', 'f.fun_cd_censo', 'tp.tup_tur_id']);
    }

    /** @return array<int,string> 38 campos. */
    private function reg50($d, string $inep): array
    {
        $c = array_fill(1, 38, '');

        $c[1] = '50';
        $c[2] = $inep;
        $c[3] = (string) $d->tup_fun_id;
        $c[4] = $this->num($d->fun_cd_censo, 12);
        $c[5] = mb_substr((string) $d->tup_tur_id, 0, 8);
        // 7 função na turma: 1 = Docente.
        $c[7] = '1';
        // 8 situação funcional, 9-33 áreas do conhecimento, 34-38 áreas EM → pendentes.

        return $c;
    }

    // ------------------------------------------------------------- Reg 60 (matrícula)

    /** @return \Illuminate\Support\Collection<int,Matricula> */
    private function matriculas(Escola $e, AnoLetivo $ano): Collection
    {
        return Matricula::query()
            ->with(['aluno:aln_id,aln_cd_inep,aln_fl_usa_transporte', 'turma:tur_id,tur_cd_inep'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q->where('tur_esc_id', $e->esc_id)->where('tur_anl_id', $ano->anl_id))
            ->get();
    }

    /** @return array<int,string> 33 campos. */
    private function reg60(Matricula $m, string $inep): array
    {
        $c = array_fill(1, 33, '');
        $a = $m->aluno;

        $c[1] = '60';
        $c[2] = $inep;
        $c[3] = (string) $m->tma_aln_id;                 // código da pessoa (aluno) no sistema
        $c[4] = $this->num($a?->aln_cd_inep, 12);        // ID Inep do aluno
        $c[5] = mb_substr((string) $m->tma_tur_id, 0, 8); // código da turma no sistema
        // 6 Inep turma / 7 código matrícula → vazios no layout.
        // 8 turma multi, 9 CH técnica → fora de escopo.
        // 10-20 AEE (por matrícula) → não modelado por matrícula (em branco).
        $c[21] = '1';                                    // escolarização fora: 1 = não recebe fora
        $c[22] = $a && $a->aln_fl_usa_transporte ? '1' : '0'; // transporte escolar público
        // 23 poder público / 24-33 tipo de veículo → módulo transporte desacoplado (em branco).

        return $c;
    }

    // ------------------------------------------------------------- helpers

    /** @param array<int,string> $campos */
    private function linha(array $campos): string
    {
        ksort($campos);

        return implode('|', $campos);
    }

    /** Texto do censo: sem acento, MAIÚSCULO, cortado no tamanho. */
    private function texto(?string $s, int $max): string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return '';
        }
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $t !== false ? $t : $s;
        $s = preg_replace('/[^\x20-\x7E]/', '', $s); // remove não-ASCII residual
        $s = mb_strtoupper($s, 'UTF-8');

        return mb_substr($s, 0, $max);
    }

    /** Só dígitos, cortado no tamanho. Vazio se não sobrar dígito. */
    private function num(?string $s, int $max): string
    {
        $d = preg_replace('/\D/', '', (string) $s);

        return $d === '' ? '' : mb_substr($d, 0, $max);
    }

    /** Data dd/mm/aaaa ou vazio. */
    private function data($valor): string
    {
        if (blank($valor)) {
            return '';
        }

        return Carbon::parse($valor)->format('d/m/Y');
    }
}
