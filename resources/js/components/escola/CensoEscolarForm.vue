<script setup lang="ts">
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CensoEscolar, CensoFormData } from '@/types/censo';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    escola: { esc_id: number; esc_nome: string; esc_apelido: string };
    censo: CensoEscolar;
    readonly?: boolean;
}>();

type TabId = 'dependencias' | 'salas' | 'internet' | 'profissionais' | 'materiais' | 'gestao';

const TABS: TabId[] = ['dependencias', 'salas', 'internet', 'profissionais', 'materiais', 'gestao'];

const n = (v: number | null): number | '' => v ?? '';

const form = useForm<CensoFormData>({
    // Seção 1
    cen_dep_almoxarifado: props.censo.cen_dep_almoxarifado,
    cen_dep_vegetacao: props.censo.cen_dep_vegetacao,
    cen_dep_auditorio: props.censo.cen_dep_auditorio,
    cen_dep_ban_pcd: props.censo.cen_dep_ban_pcd,
    cen_dep_ban_infantil: props.censo.cen_dep_ban_infantil,
    cen_dep_ban_funcionarios: props.censo.cen_dep_ban_funcionarios,
    cen_dep_ban_chuveiro: props.censo.cen_dep_ban_chuveiro,
    cen_dep_biblioteca: props.censo.cen_dep_biblioteca,
    cen_dep_cozinha: props.censo.cen_dep_cozinha,
    cen_dep_despensa: props.censo.cen_dep_despensa,
    cen_dep_dorm_aluno: props.censo.cen_dep_dorm_aluno,
    cen_dep_dorm_professor: props.censo.cen_dep_dorm_professor,
    cen_dep_lab_ciencias: props.censo.cen_dep_lab_ciencias,
    cen_dep_lab_informatica: props.censo.cen_dep_lab_informatica,
    cen_dep_lab_robotica: props.censo.cen_dep_lab_robotica,
    cen_dep_lab_profissional: props.censo.cen_dep_lab_profissional,
    cen_dep_parque_infantil: props.censo.cen_dep_parque_infantil,
    cen_dep_patio_coberto: props.censo.cen_dep_patio_coberto,
    cen_dep_patio_descoberto: props.censo.cen_dep_patio_descoberto,
    cen_dep_piscina: props.censo.cen_dep_piscina,
    cen_dep_quadra_coberta: props.censo.cen_dep_quadra_coberta,
    cen_dep_quadra_descoberta: props.censo.cen_dep_quadra_descoberta,
    cen_dep_refeitorio: props.censo.cen_dep_refeitorio,
    cen_dep_repouso_aluno: props.censo.cen_dep_repouso_aluno,
    cen_dep_sala_artes: props.censo.cen_dep_sala_artes,
    cen_dep_sala_musica: props.censo.cen_dep_sala_musica,
    cen_dep_sala_danca: props.censo.cen_dep_sala_danca,
    cen_dep_sala_multiuso: props.censo.cen_dep_sala_multiuso,
    cen_dep_terreirao: props.censo.cen_dep_terreirao,
    cen_dep_viveiro: props.censo.cen_dep_viveiro,
    cen_dep_sala_diretoria: props.censo.cen_dep_sala_diretoria,
    cen_dep_sala_leitura: props.censo.cen_dep_sala_leitura,
    cen_dep_sala_professores: props.censo.cen_dep_sala_professores,
    cen_dep_sala_aee: props.censo.cen_dep_sala_aee,
    cen_dep_sala_secretaria: props.censo.cen_dep_sala_secretaria,
    cen_dep_sala_oficinas_prof: props.censo.cen_dep_sala_oficinas_prof,
    cen_dep_estudio_gravacao: props.censo.cen_dep_estudio_gravacao,
    cen_dep_horta: props.censo.cen_dep_horta,
    cen_dep_nenhuma: props.censo.cen_dep_nenhuma,
    // Seção 2
    cen_ace_corrimao: props.censo.cen_ace_corrimao,
    cen_ace_elevador: props.censo.cen_ace_elevador,
    cen_ace_pisos_tateis: props.censo.cen_ace_pisos_tateis,
    cen_ace_portas_80cm: props.censo.cen_ace_portas_80cm,
    cen_ace_rampas: props.censo.cen_ace_rampas,
    cen_ace_sinalizacao_sonora: props.censo.cen_ace_sinalizacao_sonora,
    cen_ace_sinalizacao_tatil: props.censo.cen_ace_sinalizacao_tatil,
    cen_ace_sinalizacao_visual: props.censo.cen_ace_sinalizacao_visual,
    cen_ace_alarme_luminoso: props.censo.cen_ace_alarme_luminoso,
    cen_ace_nenhuma: props.censo.cen_ace_nenhuma,
    // Seção 3
    cen_sal_total: n(props.censo.cen_sal_total),
    cen_sal_climatizadas: n(props.censo.cen_sal_climatizadas),
    cen_sal_pcd: n(props.censo.cen_sal_pcd),
    cen_sal_dentro_predio: n(props.censo.cen_sal_dentro_predio),
    cen_sal_fora_predio: n(props.censo.cen_sal_fora_predio),
    cen_sal_cantinho_leitura: n(props.censo.cen_sal_cantinho_leitura),
    // Seção 4
    cen_eqp_antena: props.censo.cen_eqp_antena,
    cen_eqp_computadores: props.censo.cen_eqp_computadores,
    cen_eqp_copiadora: props.censo.cen_eqp_copiadora,
    cen_eqp_impressora: props.censo.cen_eqp_impressora,
    cen_eqp_multifuncional: props.censo.cen_eqp_multifuncional,
    cen_eqp_scanner: props.censo.cen_eqp_scanner,
    cen_eqp_nenhum: props.censo.cen_eqp_nenhum,
    // Seção 5
    cen_ens_dvd_qty: n(props.censo.cen_ens_dvd_qty),
    cen_ens_som_qty: n(props.censo.cen_ens_som_qty),
    cen_ens_tv_qty: n(props.censo.cen_ens_tv_qty),
    cen_ens_lousa_digital_qty: n(props.censo.cen_ens_lousa_digital_qty),
    cen_ens_projetor_qty: n(props.censo.cen_ens_projetor_qty),
    cen_ens_desktop_alunos_qty: n(props.censo.cen_ens_desktop_alunos_qty),
    cen_ens_notebook_alunos_qty: n(props.censo.cen_ens_notebook_alunos_qty),
    cen_ens_tablet_alunos_qty: n(props.censo.cen_ens_tablet_alunos_qty),
    // Seção 6
    cen_net_admin: props.censo.cen_net_admin,
    cen_net_ensino: props.censo.cen_net_ensino,
    cen_net_alunos: props.censo.cen_net_alunos,
    cen_net_comunidade: props.censo.cen_net_comunidade,
    cen_net_nenhum: props.censo.cen_net_nenhum,
    // Seção 7
    cen_net_disp_escola: props.censo.cen_net_disp_escola,
    cen_net_disp_pessoal: props.censo.cen_net_disp_pessoal,
    // Seção 8
    cen_net_tipo_cabo: props.censo.cen_net_tipo_cabo,
    cen_net_tipo_wifi: props.censo.cen_net_tipo_wifi,
    cen_net_tipo_sem_rede: props.censo.cen_net_tipo_sem_rede,
    // Seção 9
    cen_pro_agronomo_qty: n(props.censo.cen_pro_agronomo_qty),
    cen_pro_assist_social_qty: n(props.censo.cen_pro_assist_social_qty),
    cen_pro_aux_secretaria_qty: n(props.censo.cen_pro_aux_secretaria_qty),
    cen_pro_aux_servicos_qty: n(props.censo.cen_pro_aux_servicos_qty),
    cen_pro_bibliotecario_qty: n(props.censo.cen_pro_bibliotecario_qty),
    cen_pro_bombeiro_qty: n(props.censo.cen_pro_bombeiro_qty),
    cen_pro_coord_turno_qty: n(props.censo.cen_pro_coord_turno_qty),
    cen_pro_fonoaudiologo_qty: n(props.censo.cen_pro_fonoaudiologo_qty),
    cen_pro_psicologo_qty: n(props.censo.cen_pro_psicologo_qty),
    cen_pro_cozinha_qty: n(props.censo.cen_pro_cozinha_qty),
    cen_pro_coord_pedagogico_qty: n(props.censo.cen_pro_coord_pedagogico_qty),
    cen_pro_secretario_qty: n(props.censo.cen_pro_secretario_qty),
    cen_pro_seguranca_qty: n(props.censo.cen_pro_seguranca_qty),
    cen_pro_tec_laboratorio_qty: n(props.censo.cen_pro_tec_laboratorio_qty),
    cen_pro_vice_diretor_qty: n(props.censo.cen_pro_vice_diretor_qty),
    cen_pro_orientador_comun_qty: n(props.censo.cen_pro_orientador_comun_qty),
    cen_pro_interprete_libras_qty: n(props.censo.cen_pro_interprete_libras_qty),
    cen_pro_revisor_braille_qty: n(props.censo.cen_pro_revisor_braille_qty),
    // Seção 10
    cen_mat_acervo_multimidia: props.censo.cen_mat_acervo_multimidia,
    cen_mat_brinquedos_inf: props.censo.cen_mat_brinquedos_inf,
    cen_mat_cientifico: props.censo.cen_mat_cientifico,
    cen_mat_amplificacao_som: props.censo.cen_mat_amplificacao_som,
    cen_mat_audiovisual_prod: props.censo.cen_mat_audiovisual_prod,
    cen_mat_horta_equip: props.censo.cen_mat_horta_equip,
    cen_mat_instrumentos_musicais: props.censo.cen_mat_instrumentos_musicais,
    cen_mat_jogos_educativos: props.censo.cen_mat_jogos_educativos,
    cen_mat_robotica: props.censo.cen_mat_robotica,
    cen_mat_atividades_culturais: props.censo.cen_mat_atividades_culturais,
    cen_mat_educ_emocional: props.censo.cen_mat_educ_emocional,
    cen_mat_educ_profissional: props.censo.cen_mat_educ_profissional,
    cen_mat_esporte_recreacao: props.censo.cen_mat_esporte_recreacao,
    cen_mat_educ_surdos: props.censo.cen_mat_educ_surdos,
    cen_mat_educ_indigena: props.censo.cen_mat_educ_indigena,
    cen_mat_etnico_racial: props.censo.cen_mat_etnico_racial,
    cen_mat_educ_campo: props.censo.cen_mat_educ_campo,
    cen_mat_educ_quilombola: props.censo.cen_mat_educ_quilombola,
    cen_mat_educ_especial: props.censo.cen_mat_educ_especial,
    cen_mat_nenhum: props.censo.cen_mat_nenhum,
    // Seções 11–13
    cen_fl_site: props.censo.cen_fl_site,
    cen_fl_compartilha_espacos: props.censo.cen_fl_compartilha_espacos,
    cen_fl_usa_entorno: props.censo.cen_fl_usa_entorno,
    // Seção 14
    cen_org_assoc_pais: props.censo.cen_org_assoc_pais,
    cen_org_assoc_pais_mestres: props.censo.cen_org_assoc_pais_mestres,
    cen_org_conselho_escolar: props.censo.cen_org_conselho_escolar,
    cen_org_gremio: props.censo.cen_org_gremio,
    cen_org_outros: props.censo.cen_org_outros,
    cen_org_nenhum: props.censo.cen_org_nenhum,
    // Seção 15
    cen_ppp: props.censo.cen_ppp ?? '',
    // Seção 16
    cen_fl_educ_ambiental: props.censo.cen_fl_educ_ambiental,
    cen_amb_conteudo_curriculo: props.censo.cen_amb_conteudo_curriculo,
    cen_amb_comp_curricular: props.censo.cen_amb_comp_curricular,
    cen_amb_eixo_estruturante: props.censo.cen_amb_eixo_estruturante,
    cen_amb_eventos: props.censo.cen_amb_eventos,
    cen_amb_projetos_transversais: props.censo.cen_amb_projetos_transversais,
    cen_amb_nenhuma: props.censo.cen_amb_nenhuma,

    _method: 'put',
});

const activeTab = ref<TabId>('dependencias');
const isFirst = computed(() => activeTab.value === TABS[0]);
const isLast = computed(() => activeTab.value === TABS[TABS.length - 1]);
const next = () => { const i = TABS.indexOf(activeTab.value); if (i < TABS.length - 1) activeTab.value = TABS[i + 1]; };
const prev = () => { const i = TABS.indexOf(activeTab.value); if (i > 0) activeTab.value = TABS[i - 1]; };

const submit = () => {
    form.post(`/escolas/${props.escola.esc_id}/censo/${props.censo.cen_id}`, {
        preserveScroll: true,
    });
};

// Helper: field checker
const cb = (field: keyof CensoFormData) => ({
    checked: form[field] as boolean,
    'onUpdate:checked': (v: boolean | 'indeterminate') => { (form as any)[field] = !!v; },
    disabled: props.readonly,
});
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">

        <!-- Botão salvar (topo) -->
        <div v-if="!readonly" class="flex items-center justify-between">
            <Link :href="`/escolas/${escola.esc_id}/edit`">
                <Button type="button" variant="outline">Voltar</Button>
            </Link>
            <Button
                type="submit"
                :disabled="form.processing"
                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                Salvar censo
            </Button>
        </div>
        <div v-else class="flex">
            <Link :href="`/escolas/${escola.esc_id}/edit`">
                <Button type="button" variant="outline">Voltar à escola</Button>
            </Link>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="dependencias">1. Dependências</TabsTrigger>
                <TabsTrigger value="salas">2. Salas e Equip.</TabsTrigger>
                <TabsTrigger value="internet">3. Internet</TabsTrigger>
                <TabsTrigger value="profissionais">4. Profissionais</TabsTrigger>
                <TabsTrigger value="materiais">5. Materiais</TabsTrigger>
                <TabsTrigger value="gestao">6. Gestão</TabsTrigger>
            </TabsList>

            <!-- ===================== ABA 1: DEPENDÊNCIAS ===================== -->
            <TabsContent value="dependencias" class="flex flex-col gap-6">

                <!-- Seção 1: Dependências físicas -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Dependências físicas existentes na escola
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <label v-for="item in [
                            { field: 'cen_dep_almoxarifado',       label: 'Almoxarifado' },
                            { field: 'cen_dep_auditorio',          label: 'Auditório' },
                            { field: 'cen_dep_ban_pcd',            label: 'Banheiro PCD' },
                            { field: 'cen_dep_ban_infantil',       label: 'Banheiro infantil' },
                            { field: 'cen_dep_ban_funcionarios',   label: 'Banheiro func.' },
                            { field: 'cen_dep_ban_chuveiro',       label: 'Banheiro c/ chuveiro' },
                            { field: 'cen_dep_biblioteca',         label: 'Biblioteca' },
                            { field: 'cen_dep_cozinha',            label: 'Cozinha' },
                            { field: 'cen_dep_despensa',           label: 'Despensa' },
                            { field: 'cen_dep_dorm_aluno',         label: 'Dormitório aluno' },
                            { field: 'cen_dep_dorm_professor',     label: 'Dormitório prof.' },
                            { field: 'cen_dep_estudio_gravacao',   label: 'Estúdio gravação' },
                            { field: 'cen_dep_horta',              label: 'Horta' },
                            { field: 'cen_dep_lab_ciencias',       label: 'Lab. Ciências' },
                            { field: 'cen_dep_lab_informatica',    label: 'Lab. Informática' },
                            { field: 'cen_dep_lab_robotica',       label: 'Lab. Robótica' },
                            { field: 'cen_dep_lab_profissional',   label: 'Lab. Profissional' },
                            { field: 'cen_dep_parque_infantil',    label: 'Parque infantil' },
                            { field: 'cen_dep_patio_coberto',      label: 'Pátio coberto' },
                            { field: 'cen_dep_patio_descoberto',   label: 'Pátio descoberto' },
                            { field: 'cen_dep_piscina',            label: 'Piscina' },
                            { field: 'cen_dep_quadra_coberta',     label: 'Quadra coberta' },
                            { field: 'cen_dep_quadra_descoberta',  label: 'Quadra descoberta' },
                            { field: 'cen_dep_refeitorio',         label: 'Refeitório' },
                            { field: 'cen_dep_repouso_aluno',      label: 'Repouso aluno' },
                            { field: 'cen_dep_sala_artes',         label: 'Sala de artes' },
                            { field: 'cen_dep_sala_musica',        label: 'Sala de música' },
                            { field: 'cen_dep_sala_danca',         label: 'Sala de dança' },
                            { field: 'cen_dep_sala_multiuso',      label: 'Sala multiuso' },
                            { field: 'cen_dep_sala_diretoria',     label: 'Sala diretoria' },
                            { field: 'cen_dep_sala_leitura',       label: 'Sala de leitura' },
                            { field: 'cen_dep_sala_professores',   label: 'Sala professores' },
                            { field: 'cen_dep_sala_aee',           label: 'Sala AEE' },
                            { field: 'cen_dep_sala_secretaria',    label: 'Sala secretaria' },
                            { field: 'cen_dep_sala_oficinas_prof', label: 'Sala oficinas prof.' },
                            { field: 'cen_dep_terreirao',          label: 'Terreirão' },
                            { field: 'cen_dep_vegetacao',          label: 'Vegetação' },
                            { field: 'cen_dep_viveiro',            label: 'Viveiro' },
                            { field: 'cen_dep_nenhuma',            label: 'Nenhuma das anteriores' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seção 2: Acessibilidade PCD -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Recursos de acessibilidade para pessoas com deficiência (PCD)
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <label v-for="item in [
                            { field: 'cen_ace_corrimao',           label: 'Corrimão' },
                            { field: 'cen_ace_elevador',           label: 'Elevador' },
                            { field: 'cen_ace_pisos_tateis',       label: 'Pisos táteis' },
                            { field: 'cen_ace_portas_80cm',        label: 'Portas ≥ 80 cm' },
                            { field: 'cen_ace_rampas',             label: 'Rampas' },
                            { field: 'cen_ace_sinalizacao_sonora', label: 'Sinalização sonora' },
                            { field: 'cen_ace_sinalizacao_tatil',  label: 'Sinalização tátil' },
                            { field: 'cen_ace_sinalizacao_visual', label: 'Sinalização visual' },
                            { field: 'cen_ace_alarme_luminoso',    label: 'Alarme luminoso' },
                            { field: 'cen_ace_nenhuma',            label: 'Nenhuma das anteriores' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>
            </TabsContent>

            <!-- ===================== ABA 2: SALAS E EQUIPAMENTOS ===================== -->
            <TabsContent value="salas" class="flex flex-col gap-6">

                <!-- Seção 3: Salas de aula -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Quantidade de salas de aula
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="item in [
                            { field: 'cen_sal_total',             label: 'Total de salas' },
                            { field: 'cen_sal_climatizadas',      label: 'Salas climatizadas' },
                            { field: 'cen_sal_pcd',               label: 'Salas adaptadas PCD' },
                            { field: 'cen_sal_dentro_predio',     label: 'Salas dentro do prédio' },
                            { field: 'cen_sal_fora_predio',       label: 'Salas fora do prédio' },
                            { field: 'cen_sal_cantinho_leitura',  label: 'Cantinho de leitura' },
                        ]" :key="item.field" class="grid gap-2">
                            <Label :for="item.field">{{ item.label }}</Label>
                            <Input
                                :id="item.field"
                                type="number"
                                min="0"
                                max="9999"
                                :disabled="readonly"
                                :model-value="(form as any)[item.field]"
                                @update:model-value="(v: any) => (form as any)[item.field] = v === '' ? '' : Number(v)"
                            />
                            <InputError :message="(form.errors as any)[item.field]" />
                        </div>
                    </div>
                </div>

                <!-- Seção 4: Equipamentos técnico-administrativos -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Equipamentos técnico-administrativos
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <label v-for="item in [
                            { field: 'cen_eqp_antena',          label: 'Antena parabólica' },
                            { field: 'cen_eqp_computadores',    label: 'Computadores' },
                            { field: 'cen_eqp_copiadora',       label: 'Copiadora' },
                            { field: 'cen_eqp_impressora',      label: 'Impressora' },
                            { field: 'cen_eqp_multifuncional',  label: 'Multifuncional' },
                            { field: 'cen_eqp_scanner',         label: 'Scanner' },
                            { field: 'cen_eqp_nenhum',          label: 'Nenhum' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seção 5: Equipamentos de ensino -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Equipamentos de ensino (quantidade)
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="item in [
                            { field: 'cen_ens_dvd_qty',              label: 'DVD / Blu-ray' },
                            { field: 'cen_ens_som_qty',              label: 'Som' },
                            { field: 'cen_ens_tv_qty',               label: 'TV' },
                            { field: 'cen_ens_lousa_digital_qty',    label: 'Lousa digital' },
                            { field: 'cen_ens_projetor_qty',         label: 'Projetor / Datashow' },
                            { field: 'cen_ens_desktop_alunos_qty',   label: 'Desktops (alunos)' },
                            { field: 'cen_ens_notebook_alunos_qty',  label: 'Notebooks (alunos)' },
                            { field: 'cen_ens_tablet_alunos_qty',    label: 'Tablets (alunos)' },
                        ]" :key="item.field" class="grid gap-2">
                            <Label :for="item.field">{{ item.label }}</Label>
                            <Input
                                :id="item.field"
                                type="number"
                                min="0"
                                max="9999"
                                :disabled="readonly"
                                :model-value="(form as any)[item.field]"
                                @update:model-value="(v: any) => (form as any)[item.field] = v === '' ? '' : Number(v)"
                            />
                            <InputError :message="(form.errors as any)[item.field]" />
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- ===================== ABA 3: INTERNET ===================== -->
            <TabsContent value="internet" class="flex flex-col gap-6">

                <!-- Seção 6: Para quem há internet -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Internet disponível para
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <label v-for="item in [
                            { field: 'cen_net_admin',      label: 'Uso administrativo' },
                            { field: 'cen_net_ensino',     label: 'Ensino / professores' },
                            { field: 'cen_net_alunos',     label: 'Alunos' },
                            { field: 'cen_net_comunidade', label: 'Comunidade' },
                            { field: 'cen_net_nenhum',     label: 'Não há internet' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seção 7: Dispositivos dos alunos -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Dispositivos usados pelos alunos para acesso à internet
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <label v-for="item in [
                            { field: 'cen_net_disp_escola',   label: 'Dispositivo da escola' },
                            { field: 'cen_net_disp_pessoal',  label: 'Dispositivo pessoal' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seção 8: Tipo de internet -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Tipo de conexão à internet
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <label v-for="item in [
                            { field: 'cen_net_tipo_cabo',     label: 'Cabo / fibra' },
                            { field: 'cen_net_tipo_wifi',     label: 'Wi-Fi' },
                            { field: 'cen_net_tipo_sem_rede', label: 'Sem conexão à internet' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>
            </TabsContent>

            <!-- ===================== ABA 4: PROFISSIONAIS ===================== -->
            <TabsContent value="profissionais" class="flex flex-col gap-6">
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Profissionais por função (quantidade)
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="item in [
                            { field: 'cen_pro_agronomo_qty',            label: 'Agrônomo' },
                            { field: 'cen_pro_assist_social_qty',       label: 'Assistente social' },
                            { field: 'cen_pro_aux_secretaria_qty',      label: 'Aux. de secretaria' },
                            { field: 'cen_pro_aux_servicos_qty',        label: 'Aux. de serviços gerais' },
                            { field: 'cen_pro_bibliotecario_qty',       label: 'Bibliotecário' },
                            { field: 'cen_pro_bombeiro_qty',            label: 'Bombeiro' },
                            { field: 'cen_pro_coord_turno_qty',         label: 'Coord. de turno' },
                            { field: 'cen_pro_coord_pedagogico_qty',    label: 'Coord. pedagógico' },
                            { field: 'cen_pro_cozinha_qty',             label: 'Cozinheiro(a)' },
                            { field: 'cen_pro_fonoaudiologo_qty',       label: 'Fonoaudiólogo' },
                            { field: 'cen_pro_interprete_libras_qty',   label: 'Intérprete de Libras' },
                            { field: 'cen_pro_orientador_comun_qty',    label: 'Orientador comunitário' },
                            { field: 'cen_pro_psicologo_qty',           label: 'Psicólogo' },
                            { field: 'cen_pro_revisor_braille_qty',     label: 'Revisor Braille' },
                            { field: 'cen_pro_secretario_qty',          label: 'Secretário(a)' },
                            { field: 'cen_pro_seguranca_qty',           label: 'Segurança' },
                            { field: 'cen_pro_tec_laboratorio_qty',     label: 'Técnico de laboratório' },
                            { field: 'cen_pro_vice_diretor_qty',        label: 'Vice-diretor' },
                        ]" :key="item.field" class="grid gap-2">
                            <Label :for="item.field">{{ item.label }}</Label>
                            <Input
                                :id="item.field"
                                type="number"
                                min="0"
                                max="9999"
                                :disabled="readonly"
                                :model-value="(form as any)[item.field]"
                                @update:model-value="(v: any) => (form as any)[item.field] = v === '' ? '' : Number(v)"
                            />
                            <InputError :message="(form.errors as any)[item.field]" />
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- ===================== ABA 5: MATERIAIS ===================== -->
            <TabsContent value="materiais" class="flex flex-col gap-6">

                <!-- Seção 10: Materiais -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Materiais socioculturais e pedagógicos
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <label v-for="item in [
                            { field: 'cen_mat_acervo_multimidia',     label: 'Acervo multimídia' },
                            { field: 'cen_mat_amplificacao_som',      label: 'Amplificação de som' },
                            { field: 'cen_mat_atividades_culturais',  label: 'Atividades culturais' },
                            { field: 'cen_mat_audiovisual_prod',      label: 'Audiovisual / Produção' },
                            { field: 'cen_mat_brinquedos_inf',        label: 'Brinquedos infantis' },
                            { field: 'cen_mat_cientifico',            label: 'Científico' },
                            { field: 'cen_mat_educ_campo',            label: 'Ed. do campo' },
                            { field: 'cen_mat_educ_emocional',        label: 'Ed. socioemocional' },
                            { field: 'cen_mat_educ_especial',         label: 'Ed. especial' },
                            { field: 'cen_mat_educ_indigena',         label: 'Ed. indígena' },
                            { field: 'cen_mat_educ_profissional',     label: 'Ed. profissional' },
                            { field: 'cen_mat_educ_quilombola',       label: 'Ed. quilombola' },
                            { field: 'cen_mat_educ_surdos',           label: 'Ed. para surdos' },
                            { field: 'cen_mat_esporte_recreacao',     label: 'Esporte / Recreação' },
                            { field: 'cen_mat_etnico_racial',         label: 'Étnico-racial' },
                            { field: 'cen_mat_horta_equip',           label: 'Horta (equipamentos)' },
                            { field: 'cen_mat_instrumentos_musicais', label: 'Instrumentos musicais' },
                            { field: 'cen_mat_jogos_educativos',      label: 'Jogos educativos' },
                            { field: 'cen_mat_robotica',              label: 'Robótica' },
                            { field: 'cen_mat_nenhum',                label: 'Nenhum' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seções 11–13: Comunicação e espaços -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Comunicação e uso de espaços
                    </h3>
                    <div class="flex flex-col gap-3">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white p-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <Checkbox v-bind="cb('cen_fl_site')" />
                            <span class="text-sm text-slate-700 dark:text-slate-200">A escola mantém site / página na internet</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white p-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <Checkbox v-bind="cb('cen_fl_compartilha_espacos')" />
                            <span class="text-sm text-slate-700 dark:text-slate-200">A escola compartilha espaços com outras instituições</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white p-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <Checkbox v-bind="cb('cen_fl_usa_entorno')" />
                            <span class="text-sm text-slate-700 dark:text-slate-200">A escola usa o entorno para atividades pedagógicas</span>
                        </label>
                    </div>
                </div>
            </TabsContent>

            <!-- ===================== ABA 6: GESTÃO ===================== -->
            <TabsContent value="gestao" class="flex flex-col gap-6">

                <!-- Seção 14: Órgãos colegiados -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Órgãos colegiados ativos
                    </h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <label v-for="item in [
                            { field: 'cen_org_assoc_pais',         label: 'Associação de pais' },
                            { field: 'cen_org_assoc_pais_mestres', label: 'Assoc. pais e mestres' },
                            { field: 'cen_org_conselho_escolar',   label: 'Conselho escolar' },
                            { field: 'cen_org_gremio',             label: 'Grêmio estudantil' },
                            { field: 'cen_org_outros',             label: 'Outros' },
                            { field: 'cen_org_nenhum',             label: 'Nenhum' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Seção 15: PPP -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Projeto Político-Pedagógico (PPP)
                    </h3>
                    <div class="flex flex-col gap-2">
                        <Label>A escola possui PPP?</Label>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="opt in [
                                { value: 2, label: 'Sim' },
                                { value: 1, label: 'Não' },
                                { value: 0, label: 'Não possui PPP' },
                            ]" :key="opt.value"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5"
                                :class="form.cen_ppp === opt.value
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800'"
                            >
                                <input
                                    type="radio"
                                    class="hidden"
                                    :value="opt.value"
                                    :disabled="readonly"
                                    :checked="form.cen_ppp === opt.value"
                                    @change="form.cen_ppp = opt.value"
                                />
                                <span class="text-sm font-medium">{{ opt.label }}</span>
                            </label>
                        </div>
                        <InputError :message="(form.errors as any).cen_ppp" />
                    </div>
                </div>

                <!-- Seção 16: Educação ambiental -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Educação ambiental
                    </h3>
                    <div class="mb-4 flex flex-col gap-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white p-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <Checkbox v-bind="cb('cen_fl_educ_ambiental')" />
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">A escola desenvolve atividades de educação ambiental</span>
                        </label>
                    </div>

                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Como a educação ambiental é trabalhada:</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <label v-for="item in [
                            { field: 'cen_amb_conteudo_curriculo',    label: 'Conteúdo curricular' },
                            { field: 'cen_amb_comp_curricular',       label: 'Componente curricular' },
                            { field: 'cen_amb_eixo_estruturante',     label: 'Eixo estruturante' },
                            { field: 'cen_amb_eventos',               label: 'Eventos' },
                            { field: 'cen_amb_projetos_transversais', label: 'Projetos transversais' },
                            { field: 'cen_amb_nenhuma',               label: 'De nenhuma forma' },
                        ]" :key="item.field"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800"
                        >
                            <Checkbox v-bind="cb(item.field as keyof CensoFormData)" />
                            <span class="text-sm leading-tight text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                        </label>
                    </div>
                </div>
            </TabsContent>
        </Tabs>

        <!-- Navegação entre abas -->
        <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" :disabled="isFirst" @click="prev">
                <ChevronLeft class="mr-1 size-4" /> Anterior
            </Button>
            <Button v-if="!isLast" type="button" variant="outline" @click="next">
                Próximo <ChevronRight class="ml-1 size-4" />
            </Button>
        </div>
    </form>
</template>
