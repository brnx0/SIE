<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { UsersRound, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Escola { esc_id: number; esc_nome: string }
interface Tipo { id: string; label: string }

const props = defineProps<{
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    tipos: Tipo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Equipe Gestora', href: '/relatorios/equipe-gestora' },
];

const todosTipos = props.tipos.map((t) => t.id);

// "tipos" fica fora do sessionStorage de propósito: só escalares são
// persistidos (CLAUDE.md) e o form deve reabrir sempre com todas as funções
// marcadas, mostrando a equipe gestora completa por padrão.
const { restaurar, salvar } = useFiltrosRelatorio('equipe-gestora', {
    esc_id: null as number | null,
    formato: 'consolidado' as 'consolidado' | 'detalhado',
});
const salvos = restaurar();

// Escola vazia = rede inteira. Quem não é admin fica preso à própria escola.
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const tiposSel = ref<string[]>([...todosTipos]);
const formato = ref<'consolidado' | 'detalhado'>(salvos.formato ?? 'consolidado');
const gerando = ref(false);

const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function toggleTipo(id: string, marcado: boolean) {
    tiposSel.value = marcado
        ? [...tiposSel.value, id]
        : tiposSel.value.filter((t) => t !== id);
}

function gerar() {
    if (!tiposSel.value.length) return;
    gerando.value = true;
    salvar({ esc_id: escId.value, formato: formato.value });
    router.get('/relatorios/equipe-gestora/gerar', {
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        tipos: tiposSel.value,
        formato: formato.value,
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Equipe Gestora" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <UsersRound class="size-5 text-indigo-600" /> Equipe Gestora das Escolas
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Relação das escolas com a equipe gestora atual — direção, coordenação e secretaria —
                a partir das lotações ativas e vigentes hoje.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox
                        v-model="escId"
                        :items="itemsEscola"
                        :disabled="escolaTravada"
                        placeholder="Todas as escolas da rede"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ escolaTravada ? 'Você visualiza apenas a sua escola de lotação.' : 'Deixe em branco para consolidar toda a rede.' }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Formato</FormLabel>
                    <div class="flex flex-col gap-2 pt-1">
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input v-model="formato" type="radio" value="consolidado" class="mt-1 accent-indigo-600" />
                            <span>
                                <span class="font-medium">Consolidado</span>
                                <span class="block text-xs text-muted-foreground">Uma linha por escola, uma coluna por função.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input v-model="formato" type="radio" value="detalhado" class="mt-1 accent-indigo-600" />
                            <span>
                                <span class="font-medium">Detalhado</span>
                                <span class="block text-xs text-muted-foreground">Uma linha por gestor, com cargo, contato e vínculo.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel :required="true">Tipo de gestor</FormLabel>
                    <div class="flex flex-wrap gap-x-6 gap-y-2 pt-1">
                        <label
                            v-for="t in tipos"
                            :key="t.id"
                            class="flex items-center gap-2 text-sm cursor-pointer"
                        >
                            <!-- Checkbox (radix) usa checked/update:checked; com
                                 model-value o clique não surtia efeito e o filtro
                                 ia sempre com TODOS os tipos marcados. -->
                            <Checkbox
                                :checked="tiposSel.includes(t.id)"
                                @update:checked="(v: boolean | 'indeterminate') => toggleTipo(t.id, v === true)"
                            />
                            {{ t.label }}
                        </label>
                    </div>
                    <p v-if="!tiposSel.length" class="text-xs text-destructive">
                        Selecione ao menos um tipo de gestor.
                    </p>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!tiposSel.length || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
