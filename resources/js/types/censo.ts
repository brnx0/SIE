import type { AnoLetivo } from '@/types/parametro';

export type CensoStatus = 'em_preenchimento' | 'finalizado';
export type PppValue = 0 | 1 | 2 | null;

export interface CensoEscolarResumo {
    cen_id: number;
    cen_anl_id: number;
    cen_created_at: string | null;
    cen_updated_at: string | null;
    ano_letivo?: Pick<AnoLetivo, 'anl_id' | 'anl_ano' | 'anl_dt_censo'> | null;
}

export interface CensoEscolar extends CensoEscolarResumo {
    cen_esc_id: number;

    // Seção 1
    cen_dep_almoxarifado: boolean;
    cen_dep_vegetacao: boolean;
    cen_dep_auditorio: boolean;
    cen_dep_ban_pcd: boolean;
    cen_dep_ban_infantil: boolean;
    cen_dep_ban_funcionarios: boolean;
    cen_dep_ban_chuveiro: boolean;
    cen_dep_biblioteca: boolean;
    cen_dep_cozinha: boolean;
    cen_dep_despensa: boolean;
    cen_dep_dorm_aluno: boolean;
    cen_dep_dorm_professor: boolean;
    cen_dep_lab_ciencias: boolean;
    cen_dep_lab_informatica: boolean;
    cen_dep_lab_robotica: boolean;
    cen_dep_lab_profissional: boolean;
    cen_dep_parque_infantil: boolean;
    cen_dep_patio_coberto: boolean;
    cen_dep_patio_descoberto: boolean;
    cen_dep_piscina: boolean;
    cen_dep_quadra_coberta: boolean;
    cen_dep_quadra_descoberta: boolean;
    cen_dep_refeitorio: boolean;
    cen_dep_repouso_aluno: boolean;
    cen_dep_sala_artes: boolean;
    cen_dep_sala_musica: boolean;
    cen_dep_sala_danca: boolean;
    cen_dep_sala_multiuso: boolean;
    cen_dep_terreirao: boolean;
    cen_dep_viveiro: boolean;
    cen_dep_sala_diretoria: boolean;
    cen_dep_sala_leitura: boolean;
    cen_dep_sala_professores: boolean;
    cen_dep_sala_aee: boolean;
    cen_dep_sala_secretaria: boolean;
    cen_dep_sala_oficinas_prof: boolean;
    cen_dep_estudio_gravacao: boolean;
    cen_dep_horta: boolean;
    cen_dep_nenhuma: boolean;

    // Seção 2
    cen_ace_corrimao: boolean;
    cen_ace_elevador: boolean;
    cen_ace_pisos_tateis: boolean;
    cen_ace_portas_80cm: boolean;
    cen_ace_rampas: boolean;
    cen_ace_sinalizacao_sonora: boolean;
    cen_ace_sinalizacao_tatil: boolean;
    cen_ace_sinalizacao_visual: boolean;
    cen_ace_alarme_luminoso: boolean;
    cen_ace_nenhuma: boolean;

    // Seção 3
    cen_sal_total: number | null;
    cen_sal_climatizadas: number | null;
    cen_sal_pcd: number | null;
    cen_sal_dentro_predio: number | null;
    cen_sal_fora_predio: number | null;
    cen_sal_cantinho_leitura: number | null;

    // Seção 4
    cen_eqp_antena: boolean;
    cen_eqp_computadores: boolean;
    cen_eqp_copiadora: boolean;
    cen_eqp_impressora: boolean;
    cen_eqp_multifuncional: boolean;
    cen_eqp_scanner: boolean;
    cen_eqp_notebook: boolean;
    cen_eqp_nenhum: boolean;

    // Seção 5
    cen_ens_dvd_qty: number | null;
    cen_ens_som_qty: number | null;
    cen_ens_tv_qty: number | null;
    cen_ens_lousa_digital_qty: number | null;
    cen_ens_projetor_qty: number | null;
    cen_ens_desktop_alunos_qty: number | null;
    cen_ens_notebook_alunos_qty: number | null;
    cen_ens_tablet_alunos_qty: number | null;

    // Seção 6
    cen_net_admin: boolean;
    cen_net_ensino: boolean;
    cen_net_alunos: boolean;
    cen_net_comunidade: boolean;
    cen_net_nenhum: boolean;

    // Seção 7
    cen_net_disp_escola: boolean;
    cen_net_disp_pessoal: boolean;

    // Seção 8
    cen_net_tipo_cabo: boolean;
    cen_net_tipo_wifi: boolean;
    cen_net_tipo_sem_rede: boolean;

    // Seção 9
    cen_pro_agronomo_qty: number | null;
    cen_pro_assist_social_qty: number | null;
    cen_pro_aux_secretaria_qty: number | null;
    cen_pro_aux_servicos_qty: number | null;
    cen_pro_bibliotecario_qty: number | null;
    cen_pro_bombeiro_qty: number | null;
    cen_pro_coord_turno_qty: number | null;
    cen_pro_fonoaudiologo_qty: number | null;
    cen_pro_psicologo_qty: number | null;
    cen_pro_cozinha_qty: number | null;
    cen_pro_coord_pedagogico_qty: number | null;
    cen_pro_secretario_qty: number | null;
    cen_pro_seguranca_qty: number | null;
    cen_pro_tec_laboratorio_qty: number | null;
    cen_pro_vice_diretor_qty: number | null;
    cen_pro_orientador_comun_qty: number | null;
    cen_pro_interprete_libras_qty: number | null;
    cen_pro_revisor_braille_qty: number | null;

    // Seção 10
    cen_mat_acervo_multimidia: boolean;
    cen_mat_brinquedos_inf: boolean;
    cen_mat_cientifico: boolean;
    cen_mat_amplificacao_som: boolean;
    cen_mat_audiovisual_prod: boolean;
    cen_mat_horta_equip: boolean;
    cen_mat_instrumentos_musicais: boolean;
    cen_mat_jogos_educativos: boolean;
    cen_mat_robotica: boolean;
    cen_mat_atividades_culturais: boolean;
    cen_mat_educ_emocional: boolean;
    cen_mat_educ_profissional: boolean;
    cen_mat_esporte_recreacao: boolean;
    cen_mat_educ_surdos: boolean;
    cen_mat_educ_indigena: boolean;
    cen_mat_etnico_racial: boolean;
    cen_mat_educ_campo: boolean;
    cen_mat_educ_quilombola: boolean;
    cen_mat_educ_especial: boolean;
    cen_mat_nenhum: boolean;

    // Seções 11–13
    cen_fl_site: boolean;
    cen_fl_compartilha_espacos: boolean;
    cen_fl_usa_entorno: boolean;

    // Seção 14
    cen_org_assoc_pais: boolean;
    cen_org_assoc_pais_mestres: boolean;
    cen_org_conselho_escolar: boolean;
    cen_org_gremio: boolean;
    cen_org_outros: boolean;
    cen_org_nenhum: boolean;

    // Seção 15
    cen_ppp: PppValue;

    // Seção 16
    cen_fl_educ_ambiental: boolean;
    cen_amb_conteudo_curriculo: boolean;
    cen_amb_comp_curricular: boolean;
    cen_amb_eixo_estruturante: boolean;
    cen_amb_eventos: boolean;
    cen_amb_projetos_transversais: boolean;
    cen_amb_nenhuma: boolean;

    // Seção 33 — Esgotamento sanitário
    cen_esg_rede_publica: boolean;
    cen_esg_fossa_septica: boolean;
    cen_esg_fossa_rudimentar: boolean;
    cen_esg_inexistente: boolean;

    // Seção 34 — Destinação do lixo
    cen_lxd_coleta: boolean;
    cen_lxd_queima: boolean;
    cen_lxd_enterra: boolean;
    cen_lxd_destinacao_licenciada: boolean;
    cen_lxd_outra_area: boolean;

    // Seção 35 — Tratamento do lixo
    cen_lxt_separacao: boolean;
    cen_lxt_reaproveitamento: boolean;
    cen_lxt_reciclagem: boolean;
    cen_lxt_nao_faz: boolean;
}

export interface CensoFormData {
    // Seção 1
    cen_dep_almoxarifado: boolean;
    cen_dep_vegetacao: boolean;
    cen_dep_auditorio: boolean;
    cen_dep_ban_pcd: boolean;
    cen_dep_ban_infantil: boolean;
    cen_dep_ban_funcionarios: boolean;
    cen_dep_ban_chuveiro: boolean;
    cen_dep_biblioteca: boolean;
    cen_dep_cozinha: boolean;
    cen_dep_despensa: boolean;
    cen_dep_dorm_aluno: boolean;
    cen_dep_dorm_professor: boolean;
    cen_dep_lab_ciencias: boolean;
    cen_dep_lab_informatica: boolean;
    cen_dep_lab_robotica: boolean;
    cen_dep_lab_profissional: boolean;
    cen_dep_parque_infantil: boolean;
    cen_dep_patio_coberto: boolean;
    cen_dep_patio_descoberto: boolean;
    cen_dep_piscina: boolean;
    cen_dep_quadra_coberta: boolean;
    cen_dep_quadra_descoberta: boolean;
    cen_dep_refeitorio: boolean;
    cen_dep_repouso_aluno: boolean;
    cen_dep_sala_artes: boolean;
    cen_dep_sala_musica: boolean;
    cen_dep_sala_danca: boolean;
    cen_dep_sala_multiuso: boolean;
    cen_dep_terreirao: boolean;
    cen_dep_viveiro: boolean;
    cen_dep_sala_diretoria: boolean;
    cen_dep_sala_leitura: boolean;
    cen_dep_sala_professores: boolean;
    cen_dep_sala_aee: boolean;
    cen_dep_sala_secretaria: boolean;
    cen_dep_sala_oficinas_prof: boolean;
    cen_dep_estudio_gravacao: boolean;
    cen_dep_horta: boolean;
    cen_dep_nenhuma: boolean;

    // Seção 2
    cen_ace_corrimao: boolean;
    cen_ace_elevador: boolean;
    cen_ace_pisos_tateis: boolean;
    cen_ace_portas_80cm: boolean;
    cen_ace_rampas: boolean;
    cen_ace_sinalizacao_sonora: boolean;
    cen_ace_sinalizacao_tatil: boolean;
    cen_ace_sinalizacao_visual: boolean;
    cen_ace_alarme_luminoso: boolean;
    cen_ace_nenhuma: boolean;

    // Seção 3
    cen_sal_total: number | '';
    cen_sal_climatizadas: number | '';
    cen_sal_pcd: number | '';
    cen_sal_dentro_predio: number | '';
    cen_sal_fora_predio: number | '';
    cen_sal_cantinho_leitura: number | '';

    // Seção 4
    cen_eqp_antena: boolean;
    cen_eqp_computadores: boolean;
    cen_eqp_copiadora: boolean;
    cen_eqp_impressora: boolean;
    cen_eqp_multifuncional: boolean;
    cen_eqp_scanner: boolean;
    cen_eqp_notebook: boolean;
    cen_eqp_nenhum: boolean;

    // Seção 5
    cen_ens_dvd_qty: number | '';
    cen_ens_som_qty: number | '';
    cen_ens_tv_qty: number | '';
    cen_ens_lousa_digital_qty: number | '';
    cen_ens_projetor_qty: number | '';
    cen_ens_desktop_alunos_qty: number | '';
    cen_ens_notebook_alunos_qty: number | '';
    cen_ens_tablet_alunos_qty: number | '';

    // Seção 6
    cen_net_admin: boolean;
    cen_net_ensino: boolean;
    cen_net_alunos: boolean;
    cen_net_comunidade: boolean;
    cen_net_nenhum: boolean;

    // Seção 7
    cen_net_disp_escola: boolean;
    cen_net_disp_pessoal: boolean;

    // Seção 8
    cen_net_tipo_cabo: boolean;
    cen_net_tipo_wifi: boolean;
    cen_net_tipo_sem_rede: boolean;

    // Seção 9
    cen_pro_agronomo_qty: number | '';
    cen_pro_assist_social_qty: number | '';
    cen_pro_aux_secretaria_qty: number | '';
    cen_pro_aux_servicos_qty: number | '';
    cen_pro_bibliotecario_qty: number | '';
    cen_pro_bombeiro_qty: number | '';
    cen_pro_coord_turno_qty: number | '';
    cen_pro_fonoaudiologo_qty: number | '';
    cen_pro_psicologo_qty: number | '';
    cen_pro_cozinha_qty: number | '';
    cen_pro_coord_pedagogico_qty: number | '';
    cen_pro_secretario_qty: number | '';
    cen_pro_seguranca_qty: number | '';
    cen_pro_tec_laboratorio_qty: number | '';
    cen_pro_vice_diretor_qty: number | '';
    cen_pro_orientador_comun_qty: number | '';
    cen_pro_interprete_libras_qty: number | '';
    cen_pro_revisor_braille_qty: number | '';

    // Seção 10
    cen_mat_acervo_multimidia: boolean;
    cen_mat_brinquedos_inf: boolean;
    cen_mat_cientifico: boolean;
    cen_mat_amplificacao_som: boolean;
    cen_mat_audiovisual_prod: boolean;
    cen_mat_horta_equip: boolean;
    cen_mat_instrumentos_musicais: boolean;
    cen_mat_jogos_educativos: boolean;
    cen_mat_robotica: boolean;
    cen_mat_atividades_culturais: boolean;
    cen_mat_educ_emocional: boolean;
    cen_mat_educ_profissional: boolean;
    cen_mat_esporte_recreacao: boolean;
    cen_mat_educ_surdos: boolean;
    cen_mat_educ_indigena: boolean;
    cen_mat_etnico_racial: boolean;
    cen_mat_educ_campo: boolean;
    cen_mat_educ_quilombola: boolean;
    cen_mat_educ_especial: boolean;
    cen_mat_nenhum: boolean;

    // Seções 11–13
    cen_fl_site: boolean;
    cen_fl_compartilha_espacos: boolean;
    cen_fl_usa_entorno: boolean;

    // Seção 14
    cen_org_assoc_pais: boolean;
    cen_org_assoc_pais_mestres: boolean;
    cen_org_conselho_escolar: boolean;
    cen_org_gremio: boolean;
    cen_org_outros: boolean;
    cen_org_nenhum: boolean;

    // Seção 15
    cen_ppp: number | '';

    // Seção 16
    cen_fl_educ_ambiental: boolean;
    cen_amb_conteudo_curriculo: boolean;
    cen_amb_comp_curricular: boolean;
    cen_amb_eixo_estruturante: boolean;
    cen_amb_eventos: boolean;
    cen_amb_projetos_transversais: boolean;
    cen_amb_nenhuma: boolean;

    // Seção 33 — Esgotamento sanitário
    cen_esg_rede_publica: boolean;
    cen_esg_fossa_septica: boolean;
    cen_esg_fossa_rudimentar: boolean;
    cen_esg_inexistente: boolean;

    // Seção 34 — Destinação do lixo
    cen_lxd_coleta: boolean;
    cen_lxd_queima: boolean;
    cen_lxd_enterra: boolean;
    cen_lxd_destinacao_licenciada: boolean;
    cen_lxd_outra_area: boolean;

    // Seção 35 — Tratamento do lixo
    cen_lxt_separacao: boolean;
    cen_lxt_reaproveitamento: boolean;
    cen_lxt_reciclagem: boolean;
    cen_lxt_nao_faz: boolean;

    _method: 'put';
    [key: string]: unknown;
}
