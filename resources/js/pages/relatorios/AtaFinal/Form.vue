<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ScrollText, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{ anosLetivos: AnoLetivo[]; escolas: Escola[]; userEscola: Escola | null; isAdmin: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ata Final do Encerramento', href: '/relatorios/ata-final' }];

const anlId = ref<number | null>(props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);

const turmas = ref<{ tur_id: number; turma: string }[]>([]);
const carregandoTurmas = ref(false);
const gerando = ref(false);

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : { turmas: [] };
};

async function loadTurmas() {
    turmas.value = []; turId.value = null;
    if (!escId.value || !anlId.value) return;
    carregandoTurmas.value = true;
    try {
        const j = await getJson(`/relatorios/ata-final/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
        turmas.value = j.turmas ?? [];
    } finally { carregandoTurmas.value = false; }
}

onMounted(loadTurmas);
watch([escId, anlId], loadTurmas);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: t.turma })));

function gerar() {
    if (!escId.value || !anlId.value || !turId.value) return;
    gerando.value = true;
    router.get('/relatorios/ata-final/gerar', { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value }, { preserveScroll: true, onFinish: () => { gerando.value = false; } });
}
</script>

<template>
    <Head title="Ata Final do Encerramento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ScrollText class="size-5 text-indigo-600" /> Ata Final do Encerramento
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">Nota final de cada disciplina e o resultado final do aluno. Somente turmas <strong>encerradas</strong>.</p>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Turma (encerrada)</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" :placeholder="carregandoTurmas ? 'Carregando...' : (itemsTurma.length ? 'Selecione a turma...' : 'Nenhuma turma encerrada')" />
                </div>
                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-3">
                    <Button :disabled="!escId || !anlId || !turId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Ata
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
