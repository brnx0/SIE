<?php

namespace App\Models\Escola;

use App\Models\Parametro\AnoLetivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CensoEscolar extends Model
{
    protected $table = 'cfg_censo_escolar';
    protected $primaryKey = 'cen_id';

    const CREATED_AT = 'cen_created_at';
    const UPDATED_AT = 'cen_updated_at';

    protected $fillable = [
        'cen_esc_id', 'cen_anl_id',
        // Seção 1
        'cen_dep_almoxarifado', 'cen_dep_vegetacao', 'cen_dep_auditorio',
        'cen_dep_ban_pcd', 'cen_dep_ban_infantil', 'cen_dep_ban_funcionarios',
        'cen_dep_ban_chuveiro', 'cen_dep_biblioteca', 'cen_dep_cozinha',
        'cen_dep_despensa', 'cen_dep_dorm_aluno', 'cen_dep_dorm_professor',
        'cen_dep_lab_ciencias', 'cen_dep_lab_informatica', 'cen_dep_lab_robotica',
        'cen_dep_lab_profissional', 'cen_dep_parque_infantil', 'cen_dep_patio_coberto',
        'cen_dep_patio_descoberto', 'cen_dep_piscina', 'cen_dep_quadra_coberta',
        'cen_dep_quadra_descoberta', 'cen_dep_refeitorio', 'cen_dep_repouso_aluno',
        'cen_dep_sala_artes', 'cen_dep_sala_musica', 'cen_dep_sala_danca',
        'cen_dep_sala_multiuso', 'cen_dep_terreirao', 'cen_dep_viveiro',
        'cen_dep_sala_diretoria', 'cen_dep_sala_leitura', 'cen_dep_sala_professores',
        'cen_dep_sala_aee', 'cen_dep_sala_secretaria', 'cen_dep_sala_oficinas_prof',
        'cen_dep_estudio_gravacao', 'cen_dep_horta', 'cen_dep_nenhuma',
        // Seção 2
        'cen_ace_corrimao', 'cen_ace_elevador', 'cen_ace_pisos_tateis',
        'cen_ace_portas_80cm', 'cen_ace_rampas', 'cen_ace_sinalizacao_sonora',
        'cen_ace_sinalizacao_tatil', 'cen_ace_sinalizacao_visual',
        'cen_ace_alarme_luminoso', 'cen_ace_nenhuma',
        // Seção 3
        'cen_sal_total', 'cen_sal_climatizadas', 'cen_sal_pcd',
        'cen_sal_dentro_predio', 'cen_sal_fora_predio', 'cen_sal_cantinho_leitura',
        // Seção 4
        'cen_eqp_antena', 'cen_eqp_computadores', 'cen_eqp_copiadora',
        'cen_eqp_impressora', 'cen_eqp_multifuncional', 'cen_eqp_scanner',
        'cen_eqp_notebook', 'cen_eqp_nenhum',
        // Seção 5
        'cen_ens_dvd_qty', 'cen_ens_som_qty', 'cen_ens_tv_qty',
        'cen_ens_lousa_digital_qty', 'cen_ens_projetor_qty',
        'cen_ens_desktop_alunos_qty', 'cen_ens_notebook_alunos_qty',
        'cen_ens_tablet_alunos_qty',
        // Seção 6
        'cen_net_admin', 'cen_net_ensino', 'cen_net_alunos',
        'cen_net_comunidade', 'cen_net_nenhum',
        // Seção 7
        'cen_net_disp_escola', 'cen_net_disp_pessoal',
        // Seção 8
        'cen_net_tipo_cabo', 'cen_net_tipo_wifi', 'cen_net_tipo_sem_rede',
        // Seção 9
        'cen_pro_agronomo_qty', 'cen_pro_assist_social_qty', 'cen_pro_aux_secretaria_qty',
        'cen_pro_aux_servicos_qty', 'cen_pro_bibliotecario_qty', 'cen_pro_bombeiro_qty',
        'cen_pro_coord_turno_qty', 'cen_pro_fonoaudiologo_qty', 'cen_pro_psicologo_qty',
        'cen_pro_cozinha_qty', 'cen_pro_coord_pedagogico_qty', 'cen_pro_secretario_qty',
        'cen_pro_seguranca_qty', 'cen_pro_tec_laboratorio_qty', 'cen_pro_vice_diretor_qty',
        'cen_pro_orientador_comun_qty', 'cen_pro_interprete_libras_qty',
        'cen_pro_revisor_braille_qty',
        // Seção 10
        'cen_mat_acervo_multimidia', 'cen_mat_brinquedos_inf', 'cen_mat_cientifico',
        'cen_mat_amplificacao_som', 'cen_mat_audiovisual_prod', 'cen_mat_horta_equip',
        'cen_mat_instrumentos_musicais', 'cen_mat_jogos_educativos', 'cen_mat_robotica',
        'cen_mat_atividades_culturais', 'cen_mat_educ_emocional', 'cen_mat_educ_profissional',
        'cen_mat_esporte_recreacao', 'cen_mat_educ_surdos', 'cen_mat_educ_indigena',
        'cen_mat_etnico_racial', 'cen_mat_educ_campo', 'cen_mat_educ_quilombola',
        'cen_mat_educ_especial', 'cen_mat_nenhum',
        // Seções 11–13
        'cen_fl_site', 'cen_fl_compartilha_espacos', 'cen_fl_usa_entorno',
        // Seção 14
        'cen_org_assoc_pais', 'cen_org_assoc_pais_mestres', 'cen_org_conselho_escolar',
        'cen_org_gremio', 'cen_org_outros', 'cen_org_nenhum',
        // Seção 15
        'cen_ppp',
        // Seção 16
        'cen_fl_educ_ambiental', 'cen_amb_conteudo_curriculo', 'cen_amb_comp_curricular',
        'cen_amb_eixo_estruturante', 'cen_amb_eventos', 'cen_amb_projetos_transversais',
        'cen_amb_nenhuma',
        // Seção 33 — Esgotamento sanitário
        'cen_esg_rede_publica', 'cen_esg_fossa_septica', 'cen_esg_fossa_rudimentar', 'cen_esg_inexistente',
        // Seção 34 — Destinação do lixo
        'cen_lxd_coleta', 'cen_lxd_queima', 'cen_lxd_enterra',
        'cen_lxd_destinacao_licenciada', 'cen_lxd_outra_area',
        // Seção 35 — Tratamento do lixo
        'cen_lxt_separacao', 'cen_lxt_reaproveitamento', 'cen_lxt_reciclagem', 'cen_lxt_nao_faz',
    ];

    protected $casts = [
        // booleans seção 1
        'cen_dep_almoxarifado'       => 'boolean', 'cen_dep_vegetacao'          => 'boolean',
        'cen_dep_auditorio'          => 'boolean', 'cen_dep_ban_pcd'            => 'boolean',
        'cen_dep_ban_infantil'       => 'boolean', 'cen_dep_ban_funcionarios'   => 'boolean',
        'cen_dep_ban_chuveiro'       => 'boolean', 'cen_dep_biblioteca'         => 'boolean',
        'cen_dep_cozinha'            => 'boolean', 'cen_dep_despensa'           => 'boolean',
        'cen_dep_dorm_aluno'         => 'boolean', 'cen_dep_dorm_professor'     => 'boolean',
        'cen_dep_lab_ciencias'       => 'boolean', 'cen_dep_lab_informatica'    => 'boolean',
        'cen_dep_lab_robotica'       => 'boolean', 'cen_dep_lab_profissional'   => 'boolean',
        'cen_dep_parque_infantil'    => 'boolean', 'cen_dep_patio_coberto'      => 'boolean',
        'cen_dep_patio_descoberto'   => 'boolean', 'cen_dep_piscina'            => 'boolean',
        'cen_dep_quadra_coberta'     => 'boolean', 'cen_dep_quadra_descoberta'  => 'boolean',
        'cen_dep_refeitorio'         => 'boolean', 'cen_dep_repouso_aluno'      => 'boolean',
        'cen_dep_sala_artes'         => 'boolean', 'cen_dep_sala_musica'        => 'boolean',
        'cen_dep_sala_danca'         => 'boolean', 'cen_dep_sala_multiuso'      => 'boolean',
        'cen_dep_terreirao'          => 'boolean', 'cen_dep_viveiro'            => 'boolean',
        'cen_dep_sala_diretoria'     => 'boolean', 'cen_dep_sala_leitura'       => 'boolean',
        'cen_dep_sala_professores'   => 'boolean', 'cen_dep_sala_aee'           => 'boolean',
        'cen_dep_sala_secretaria'    => 'boolean', 'cen_dep_sala_oficinas_prof' => 'boolean',
        'cen_dep_estudio_gravacao'   => 'boolean', 'cen_dep_horta'              => 'boolean',
        'cen_dep_nenhuma'            => 'boolean',
        // booleans seção 2
        'cen_ace_corrimao'           => 'boolean', 'cen_ace_elevador'           => 'boolean',
        'cen_ace_pisos_tateis'       => 'boolean', 'cen_ace_portas_80cm'        => 'boolean',
        'cen_ace_rampas'             => 'boolean', 'cen_ace_sinalizacao_sonora' => 'boolean',
        'cen_ace_sinalizacao_tatil'  => 'boolean', 'cen_ace_sinalizacao_visual' => 'boolean',
        'cen_ace_alarme_luminoso'    => 'boolean', 'cen_ace_nenhuma'            => 'boolean',
        // integers seção 3
        'cen_sal_total'              => 'integer', 'cen_sal_climatizadas'       => 'integer',
        'cen_sal_pcd'                => 'integer', 'cen_sal_dentro_predio'      => 'integer',
        'cen_sal_fora_predio'        => 'integer', 'cen_sal_cantinho_leitura'   => 'integer',
        // booleans seção 4
        'cen_eqp_antena'             => 'boolean', 'cen_eqp_computadores'       => 'boolean',
        'cen_eqp_copiadora'          => 'boolean', 'cen_eqp_impressora'         => 'boolean',
        'cen_eqp_multifuncional'     => 'boolean', 'cen_eqp_scanner'            => 'boolean',
        'cen_eqp_notebook'           => 'boolean', 'cen_eqp_nenhum'             => 'boolean',
        // integers seção 5
        'cen_ens_dvd_qty'            => 'integer', 'cen_ens_som_qty'            => 'integer',
        'cen_ens_tv_qty'             => 'integer', 'cen_ens_lousa_digital_qty'  => 'integer',
        'cen_ens_projetor_qty'       => 'integer', 'cen_ens_desktop_alunos_qty' => 'integer',
        'cen_ens_notebook_alunos_qty'=> 'integer', 'cen_ens_tablet_alunos_qty'  => 'integer',
        // booleans seção 6-8
        'cen_net_admin'              => 'boolean', 'cen_net_ensino'             => 'boolean',
        'cen_net_alunos'             => 'boolean', 'cen_net_comunidade'         => 'boolean',
        'cen_net_nenhum'             => 'boolean', 'cen_net_disp_escola'        => 'boolean',
        'cen_net_disp_pessoal'       => 'boolean', 'cen_net_tipo_cabo'          => 'boolean',
        'cen_net_tipo_wifi'          => 'boolean', 'cen_net_tipo_sem_rede'      => 'boolean',
        // integers seção 9
        'cen_pro_agronomo_qty'       => 'integer', 'cen_pro_assist_social_qty'  => 'integer',
        'cen_pro_aux_secretaria_qty' => 'integer', 'cen_pro_aux_servicos_qty'   => 'integer',
        'cen_pro_bibliotecario_qty'  => 'integer', 'cen_pro_bombeiro_qty'       => 'integer',
        'cen_pro_coord_turno_qty'    => 'integer', 'cen_pro_fonoaudiologo_qty'  => 'integer',
        'cen_pro_psicologo_qty'      => 'integer', 'cen_pro_cozinha_qty'        => 'integer',
        'cen_pro_coord_pedagogico_qty'=> 'integer','cen_pro_secretario_qty'     => 'integer',
        'cen_pro_seguranca_qty'      => 'integer', 'cen_pro_tec_laboratorio_qty'=> 'integer',
        'cen_pro_vice_diretor_qty'   => 'integer', 'cen_pro_orientador_comun_qty'=>'integer',
        'cen_pro_interprete_libras_qty'=>'integer','cen_pro_revisor_braille_qty'=> 'integer',
        // booleans seção 10
        'cen_mat_acervo_multimidia'  => 'boolean', 'cen_mat_brinquedos_inf'     => 'boolean',
        'cen_mat_cientifico'         => 'boolean', 'cen_mat_amplificacao_som'   => 'boolean',
        'cen_mat_audiovisual_prod'   => 'boolean', 'cen_mat_horta_equip'        => 'boolean',
        'cen_mat_instrumentos_musicais'=>'boolean','cen_mat_jogos_educativos'   => 'boolean',
        'cen_mat_robotica'           => 'boolean', 'cen_mat_atividades_culturais'=> 'boolean',
        'cen_mat_educ_emocional'     => 'boolean', 'cen_mat_educ_profissional'  => 'boolean',
        'cen_mat_esporte_recreacao'  => 'boolean', 'cen_mat_educ_surdos'        => 'boolean',
        'cen_mat_educ_indigena'      => 'boolean', 'cen_mat_etnico_racial'      => 'boolean',
        'cen_mat_educ_campo'         => 'boolean', 'cen_mat_educ_quilombola'    => 'boolean',
        'cen_mat_educ_especial'      => 'boolean', 'cen_mat_nenhum'             => 'boolean',
        // seções 11-13
        'cen_fl_site'                => 'boolean', 'cen_fl_compartilha_espacos' => 'boolean',
        'cen_fl_usa_entorno'         => 'boolean',
        // seção 14
        'cen_org_assoc_pais'         => 'boolean', 'cen_org_assoc_pais_mestres' => 'boolean',
        'cen_org_conselho_escolar'   => 'boolean', 'cen_org_gremio'             => 'boolean',
        'cen_org_outros'             => 'boolean', 'cen_org_nenhum'             => 'boolean',
        // seção 15
        'cen_ppp'                    => 'integer',
        // seção 16
        'cen_fl_educ_ambiental'      => 'boolean', 'cen_amb_conteudo_curriculo' => 'boolean',
        'cen_amb_comp_curricular'    => 'boolean', 'cen_amb_eixo_estruturante'  => 'boolean',
        'cen_amb_eventos'            => 'boolean', 'cen_amb_projetos_transversais'=>'boolean',
        'cen_amb_nenhuma'            => 'boolean',
        // seção 33
        'cen_esg_rede_publica'       => 'boolean', 'cen_esg_fossa_septica'      => 'boolean',
        'cen_esg_fossa_rudimentar'   => 'boolean', 'cen_esg_inexistente'        => 'boolean',
        // seção 34
        'cen_lxd_coleta'             => 'boolean', 'cen_lxd_queima'             => 'boolean',
        'cen_lxd_enterra'            => 'boolean', 'cen_lxd_destinacao_licenciada' => 'boolean',
        'cen_lxd_outra_area'         => 'boolean',
        // seção 35
        'cen_lxt_separacao'          => 'boolean', 'cen_lxt_reaproveitamento'   => 'boolean',
        'cen_lxt_reciclagem'         => 'boolean', 'cen_lxt_nao_faz'            => 'boolean',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'cen_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'cen_anl_id', 'anl_id');
    }

    /** Retorna 'em_preenchimento' | 'finalizado' conforme prazo do ano letivo. */
    public function getStatusAttribute(): string
    {
        $prazo = $this->anoLetivo?->anl_dt_censo;
        if ($prazo && Carbon::today()->isAfter($prazo)) {
            return 'finalizado';
        }
        return 'em_preenchimento';
    }
}
