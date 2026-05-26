<?php

namespace App\Http\Requests\Escola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCensoEscolarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Seção 1 — Dependências físicas
            'cen_dep_almoxarifado'       => ['boolean'],
            'cen_dep_vegetacao'          => ['boolean'],
            'cen_dep_auditorio'          => ['boolean'],
            'cen_dep_ban_pcd'            => ['boolean'],
            'cen_dep_ban_infantil'       => ['boolean'],
            'cen_dep_ban_funcionarios'   => ['boolean'],
            'cen_dep_ban_chuveiro'       => ['boolean'],
            'cen_dep_biblioteca'         => ['boolean'],
            'cen_dep_cozinha'            => ['boolean'],
            'cen_dep_despensa'           => ['boolean'],
            'cen_dep_dorm_aluno'         => ['boolean'],
            'cen_dep_dorm_professor'     => ['boolean'],
            'cen_dep_lab_ciencias'       => ['boolean'],
            'cen_dep_lab_informatica'    => ['boolean'],
            'cen_dep_lab_robotica'       => ['boolean'],
            'cen_dep_lab_profissional'   => ['boolean'],
            'cen_dep_parque_infantil'    => ['boolean'],
            'cen_dep_patio_coberto'      => ['boolean'],
            'cen_dep_patio_descoberto'   => ['boolean'],
            'cen_dep_piscina'            => ['boolean'],
            'cen_dep_quadra_coberta'     => ['boolean'],
            'cen_dep_quadra_descoberta'  => ['boolean'],
            'cen_dep_refeitorio'         => ['boolean'],
            'cen_dep_repouso_aluno'      => ['boolean'],
            'cen_dep_sala_artes'         => ['boolean'],
            'cen_dep_sala_musica'        => ['boolean'],
            'cen_dep_sala_danca'         => ['boolean'],
            'cen_dep_sala_multiuso'      => ['boolean'],
            'cen_dep_terreirao'          => ['boolean'],
            'cen_dep_viveiro'            => ['boolean'],
            'cen_dep_sala_diretoria'     => ['boolean'],
            'cen_dep_sala_leitura'       => ['boolean'],
            'cen_dep_sala_professores'   => ['boolean'],
            'cen_dep_sala_aee'           => ['boolean'],
            'cen_dep_sala_secretaria'    => ['boolean'],
            'cen_dep_sala_oficinas_prof' => ['boolean'],
            'cen_dep_estudio_gravacao'   => ['boolean'],
            'cen_dep_horta'              => ['boolean'],
            'cen_dep_nenhuma'            => ['boolean'],

            // Seção 2 — Acessibilidade PCD
            'cen_ace_corrimao'           => ['boolean'],
            'cen_ace_elevador'           => ['boolean'],
            'cen_ace_pisos_tateis'       => ['boolean'],
            'cen_ace_portas_80cm'        => ['boolean'],
            'cen_ace_rampas'             => ['boolean'],
            'cen_ace_sinalizacao_sonora' => ['boolean'],
            'cen_ace_sinalizacao_tatil'  => ['boolean'],
            'cen_ace_sinalizacao_visual' => ['boolean'],
            'cen_ace_alarme_luminoso'    => ['boolean'],
            'cen_ace_nenhuma'            => ['boolean'],

            // Seção 3 — Salas de aula
            'cen_sal_total'              => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_sal_climatizadas'       => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_sal_pcd'                => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_sal_dentro_predio'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_sal_fora_predio'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_sal_cantinho_leitura'   => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Seção 4 — Equipamentos técnico-administrativos
            'cen_eqp_antena'             => ['boolean'],
            'cen_eqp_computadores'       => ['boolean'],
            'cen_eqp_copiadora'          => ['boolean'],
            'cen_eqp_impressora'         => ['boolean'],
            'cen_eqp_multifuncional'     => ['boolean'],
            'cen_eqp_scanner'            => ['boolean'],
            'cen_eqp_nenhum'             => ['boolean'],

            // Seção 5 — Equipamentos de ensino (quantidades)
            'cen_ens_dvd_qty'            => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_som_qty'            => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_tv_qty'             => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_lousa_digital_qty'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_projetor_qty'       => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_desktop_alunos_qty' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_notebook_alunos_qty'=> ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_ens_tablet_alunos_qty'  => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Seção 6 — Acesso à internet
            'cen_net_admin'              => ['boolean'],
            'cen_net_ensino'             => ['boolean'],
            'cen_net_alunos'             => ['boolean'],
            'cen_net_comunidade'         => ['boolean'],
            'cen_net_nenhum'             => ['boolean'],

            // Seção 7 — Dispositivos para internet
            'cen_net_disp_escola'        => ['boolean'],
            'cen_net_disp_pessoal'       => ['boolean'],

            // Seção 8 — Tipo de internet
            'cen_net_tipo_cabo'          => ['boolean'],
            'cen_net_tipo_wifi'          => ['boolean'],
            'cen_net_tipo_sem_rede'      => ['boolean'],

            // Seção 9 — Profissionais por função
            'cen_pro_agronomo_qty'          => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_assist_social_qty'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_aux_secretaria_qty'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_aux_servicos_qty'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_bibliotecario_qty'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_bombeiro_qty'          => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_coord_turno_qty'       => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_fonoaudiologo_qty'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_psicologo_qty'         => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_cozinha_qty'           => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_coord_pedagogico_qty'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_secretario_qty'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_seguranca_qty'         => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_tec_laboratorio_qty'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_vice_diretor_qty'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_orientador_comun_qty'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_interprete_libras_qty' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cen_pro_revisor_braille_qty'   => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Seção 10 — Materiais socioculturais/pedagógicos
            'cen_mat_acervo_multimidia'      => ['boolean'],
            'cen_mat_brinquedos_inf'         => ['boolean'],
            'cen_mat_cientifico'             => ['boolean'],
            'cen_mat_amplificacao_som'       => ['boolean'],
            'cen_mat_audiovisual_prod'       => ['boolean'],
            'cen_mat_horta_equip'            => ['boolean'],
            'cen_mat_instrumentos_musicais'  => ['boolean'],
            'cen_mat_jogos_educativos'       => ['boolean'],
            'cen_mat_robotica'               => ['boolean'],
            'cen_mat_atividades_culturais'   => ['boolean'],
            'cen_mat_educ_emocional'         => ['boolean'],
            'cen_mat_educ_profissional'      => ['boolean'],
            'cen_mat_esporte_recreacao'      => ['boolean'],
            'cen_mat_educ_surdos'            => ['boolean'],
            'cen_mat_educ_indigena'          => ['boolean'],
            'cen_mat_etnico_racial'          => ['boolean'],
            'cen_mat_educ_campo'             => ['boolean'],
            'cen_mat_educ_quilombola'        => ['boolean'],
            'cen_mat_educ_especial'          => ['boolean'],
            'cen_mat_nenhum'                 => ['boolean'],

            // Seções 11–13
            'cen_fl_site'                => ['boolean'],
            'cen_fl_compartilha_espacos' => ['boolean'],
            'cen_fl_usa_entorno'         => ['boolean'],

            // Seção 14 — Órgãos colegiados
            'cen_org_assoc_pais'         => ['boolean'],
            'cen_org_assoc_pais_mestres' => ['boolean'],
            'cen_org_conselho_escolar'   => ['boolean'],
            'cen_org_gremio'             => ['boolean'],
            'cen_org_outros'             => ['boolean'],
            'cen_org_nenhum'             => ['boolean'],

            // Seção 15 — PPP
            'cen_ppp'                    => ['nullable', 'integer', Rule::in([0, 1, 2])],

            // Seção 16 — Educação ambiental
            'cen_fl_educ_ambiental'          => ['boolean'],
            'cen_amb_conteudo_curriculo'     => ['boolean'],
            'cen_amb_comp_curricular'        => ['boolean'],
            'cen_amb_eixo_estruturante'      => ['boolean'],
            'cen_amb_eventos'                => ['boolean'],
            'cen_amb_projetos_transversais'  => ['boolean'],
            'cen_amb_nenhuma'                => ['boolean'],
        ];
    }
}
