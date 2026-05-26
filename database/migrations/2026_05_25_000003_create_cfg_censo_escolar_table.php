<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_censo_escolar', function (Blueprint $t) {
            $t->bigIncrements('cen_id');

            $t->unsignedBigInteger('cen_esc_id');
            $t->foreign('cen_esc_id')->references('esc_id')->on('edu_escola');

            $t->unsignedBigInteger('cen_anl_id');
            $t->foreign('cen_anl_id')->references('anl_id')->on('cfg_ano_letivo');

            $t->unique(['cen_esc_id', 'cen_anl_id']);

            // -------------------------------------------------------
            // SEÇÃO 1 — Dependências físicas
            // -------------------------------------------------------
            $t->boolean('cen_dep_almoxarifado')->default(false);
            $t->boolean('cen_dep_vegetacao')->default(false);
            $t->boolean('cen_dep_auditorio')->default(false);
            $t->boolean('cen_dep_ban_pcd')->default(false);
            $t->boolean('cen_dep_ban_infantil')->default(false);
            $t->boolean('cen_dep_ban_funcionarios')->default(false);
            $t->boolean('cen_dep_ban_chuveiro')->default(false);
            $t->boolean('cen_dep_biblioteca')->default(false);
            $t->boolean('cen_dep_cozinha')->default(false);
            $t->boolean('cen_dep_despensa')->default(false);
            $t->boolean('cen_dep_dorm_aluno')->default(false);
            $t->boolean('cen_dep_dorm_professor')->default(false);
            $t->boolean('cen_dep_lab_ciencias')->default(false);
            $t->boolean('cen_dep_lab_informatica')->default(false);
            $t->boolean('cen_dep_lab_robotica')->default(false);
            $t->boolean('cen_dep_lab_profissional')->default(false);
            $t->boolean('cen_dep_parque_infantil')->default(false);
            $t->boolean('cen_dep_patio_coberto')->default(false);
            $t->boolean('cen_dep_patio_descoberto')->default(false);
            $t->boolean('cen_dep_piscina')->default(false);
            $t->boolean('cen_dep_quadra_coberta')->default(false);
            $t->boolean('cen_dep_quadra_descoberta')->default(false);
            $t->boolean('cen_dep_refeitorio')->default(false);
            $t->boolean('cen_dep_repouso_aluno')->default(false);
            $t->boolean('cen_dep_sala_artes')->default(false);
            $t->boolean('cen_dep_sala_musica')->default(false);
            $t->boolean('cen_dep_sala_danca')->default(false);
            $t->boolean('cen_dep_sala_multiuso')->default(false);
            $t->boolean('cen_dep_terreirao')->default(false);
            $t->boolean('cen_dep_viveiro')->default(false);
            $t->boolean('cen_dep_sala_diretoria')->default(false);
            $t->boolean('cen_dep_sala_leitura')->default(false);
            $t->boolean('cen_dep_sala_professores')->default(false);
            $t->boolean('cen_dep_sala_aee')->default(false);
            $t->boolean('cen_dep_sala_secretaria')->default(false);
            $t->boolean('cen_dep_sala_oficinas_prof')->default(false);
            $t->boolean('cen_dep_estudio_gravacao')->default(false);
            $t->boolean('cen_dep_horta')->default(false);
            $t->boolean('cen_dep_nenhuma')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 2 — Acessibilidade PCD
            // -------------------------------------------------------
            $t->boolean('cen_ace_corrimao')->default(false);
            $t->boolean('cen_ace_elevador')->default(false);
            $t->boolean('cen_ace_pisos_tateis')->default(false);
            $t->boolean('cen_ace_portas_80cm')->default(false);
            $t->boolean('cen_ace_rampas')->default(false);
            $t->boolean('cen_ace_sinalizacao_sonora')->default(false);
            $t->boolean('cen_ace_sinalizacao_tatil')->default(false);
            $t->boolean('cen_ace_sinalizacao_visual')->default(false);
            $t->boolean('cen_ace_alarme_luminoso')->default(false);
            $t->boolean('cen_ace_nenhuma')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 3 — Quantidade de salas de aula
            // -------------------------------------------------------
            $t->unsignedSmallInteger('cen_sal_total')->nullable();
            $t->unsignedSmallInteger('cen_sal_climatizadas')->nullable();
            $t->unsignedSmallInteger('cen_sal_pcd')->nullable();
            $t->unsignedSmallInteger('cen_sal_dentro_predio')->nullable();
            $t->unsignedSmallInteger('cen_sal_fora_predio')->nullable();
            $t->unsignedSmallInteger('cen_sal_cantinho_leitura')->nullable();

            // -------------------------------------------------------
            // SEÇÃO 4 — Equipamentos técnico-administrativos
            // -------------------------------------------------------
            $t->boolean('cen_eqp_antena')->default(false);
            $t->boolean('cen_eqp_computadores')->default(false);
            $t->boolean('cen_eqp_copiadora')->default(false);
            $t->boolean('cen_eqp_impressora')->default(false);
            $t->boolean('cen_eqp_multifuncional')->default(false);
            $t->boolean('cen_eqp_scanner')->default(false);
            $t->boolean('cen_eqp_nenhum')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 5 — Equipamentos de ensino (quantidades)
            // -------------------------------------------------------
            $t->unsignedSmallInteger('cen_ens_dvd_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_som_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_tv_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_lousa_digital_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_projetor_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_desktop_alunos_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_notebook_alunos_qty')->nullable();
            $t->unsignedSmallInteger('cen_ens_tablet_alunos_qty')->nullable();

            // -------------------------------------------------------
            // SEÇÃO 6 — Acesso à internet
            // -------------------------------------------------------
            $t->boolean('cen_net_admin')->default(false);
            $t->boolean('cen_net_ensino')->default(false);
            $t->boolean('cen_net_alunos')->default(false);
            $t->boolean('cen_net_comunidade')->default(false);
            $t->boolean('cen_net_nenhum')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 7 — Dispositivos que alunos usam para internet
            // -------------------------------------------------------
            $t->boolean('cen_net_disp_escola')->default(false);
            $t->boolean('cen_net_disp_pessoal')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 8 — Tipo de internet
            // -------------------------------------------------------
            $t->boolean('cen_net_tipo_cabo')->default(false);
            $t->boolean('cen_net_tipo_wifi')->default(false);
            $t->boolean('cen_net_tipo_sem_rede')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 9 — Profissionais por função (quantidades)
            // -------------------------------------------------------
            $t->unsignedSmallInteger('cen_pro_agronomo_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_assist_social_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_aux_secretaria_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_aux_servicos_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_bibliotecario_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_bombeiro_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_coord_turno_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_fonoaudiologo_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_psicologo_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_cozinha_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_coord_pedagogico_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_secretario_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_seguranca_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_tec_laboratorio_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_vice_diretor_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_orientador_comun_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_interprete_libras_qty')->nullable();
            $t->unsignedSmallInteger('cen_pro_revisor_braille_qty')->nullable();

            // -------------------------------------------------------
            // SEÇÃO 10 — Materiais socioculturais/pedagógicos
            // -------------------------------------------------------
            $t->boolean('cen_mat_acervo_multimidia')->default(false);
            $t->boolean('cen_mat_brinquedos_inf')->default(false);
            $t->boolean('cen_mat_cientifico')->default(false);
            $t->boolean('cen_mat_amplificacao_som')->default(false);
            $t->boolean('cen_mat_audiovisual_prod')->default(false);
            $t->boolean('cen_mat_horta_equip')->default(false);
            $t->boolean('cen_mat_instrumentos_musicais')->default(false);
            $t->boolean('cen_mat_jogos_educativos')->default(false);
            $t->boolean('cen_mat_robotica')->default(false);
            $t->boolean('cen_mat_atividades_culturais')->default(false);
            $t->boolean('cen_mat_educ_emocional')->default(false);
            $t->boolean('cen_mat_educ_profissional')->default(false);
            $t->boolean('cen_mat_esporte_recreacao')->default(false);
            $t->boolean('cen_mat_educ_surdos')->default(false);
            $t->boolean('cen_mat_educ_indigena')->default(false);
            $t->boolean('cen_mat_etnico_racial')->default(false);
            $t->boolean('cen_mat_educ_campo')->default(false);
            $t->boolean('cen_mat_educ_quilombola')->default(false);
            $t->boolean('cen_mat_educ_especial')->default(false);
            $t->boolean('cen_mat_nenhum')->default(false);

            // -------------------------------------------------------
            // SEÇÕES 11–13 — Comunicação e espaços
            // -------------------------------------------------------
            $t->boolean('cen_fl_site')->default(false);
            $t->boolean('cen_fl_compartilha_espacos')->default(false);
            $t->boolean('cen_fl_usa_entorno')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 14 — Órgãos colegiados
            // -------------------------------------------------------
            $t->boolean('cen_org_assoc_pais')->default(false);
            $t->boolean('cen_org_assoc_pais_mestres')->default(false);
            $t->boolean('cen_org_conselho_escolar')->default(false);
            $t->boolean('cen_org_gremio')->default(false);
            $t->boolean('cen_org_outros')->default(false);
            $t->boolean('cen_org_nenhum')->default(false);

            // -------------------------------------------------------
            // SEÇÃO 15 — PPP  (0 = não possui | 1 = não | 2 = sim)
            // -------------------------------------------------------
            $t->unsignedTinyInteger('cen_ppp')->nullable();

            // -------------------------------------------------------
            // SEÇÃO 16 — Educação ambiental
            // -------------------------------------------------------
            $t->boolean('cen_fl_educ_ambiental')->default(false);
            $t->boolean('cen_amb_conteudo_curriculo')->default(false);
            $t->boolean('cen_amb_comp_curricular')->default(false);
            $t->boolean('cen_amb_eixo_estruturante')->default(false);
            $t->boolean('cen_amb_eventos')->default(false);
            $t->boolean('cen_amb_projetos_transversais')->default(false);
            $t->boolean('cen_amb_nenhuma')->default(false);

            // -------------------------------------------------------
            // Metadata
            // -------------------------------------------------------
            $t->timestamp('cen_created_at')->nullable();
            $t->timestamp('cen_updated_at')->nullable();

            $t->index('cen_esc_id');
            $t->index('cen_anl_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_censo_escolar');
    }
};
